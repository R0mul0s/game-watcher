<?php

/**
 * Data grafu vývoje ceny na detailu hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Itad\ItadHistory;
use App\Domain\Itad\ItadPriceChange;
use App\Enums\Platform;
use App\Enums\PriceScope;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Denní řady (R28): nejlevnější obchod pro PC a Steam z historie ITAD (zpětně
 * až chart.history_days dní), obchody s klíči a produkty PS Store z vlastní
 * price_history (sbírá se od nasazení cronu) a jako srovnání historické
 * minimum obchodů s klíči z gg.deals. Hodnota dne = nejnižší cena
 * toho dne (místní den). Vše v měně zobrazení dnešním kurzem ČNB — historické
 * kurzy se neukládají (R14). Výpadek ITAD graf neskryje, jen bez řad z ITAD.
 * Hra musí mít načtenou vazbu storeListings.
 */
final class PriceChart
{
    /** Klíče řad — barvu a název řady volí stránka. */
    public const SERIES_OFFICIAL = 'official';

    public const SERIES_STEAM = 'steam';

    public const SERIES_KEYSHOPS = 'keyshops';

    /** Historické minimum obchodů s klíči z gg.deals — vodorovná čára (vlastní historie klíčů je krátká). */
    public const SERIES_KEYSHOPS_LOW = 'keyshops_low';

    public function __construct(
        private readonly ItadHistory $history,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Dny, řady a cílová cena pro graf.
     *
     * @return array{days: list<string>, series: list<array{key: string, name: string|null, platform: string, data: list<float|null>}>, target: float|null, currency: string, itadAvailable: bool, ownSince: string|null}
     */
    public function build(Game $game, string $currency, ?WatchlistItem $item): array
    {
        $zone = (string) config('games.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();
        $start = ItadHistory::start();

        $changes = $this->history->forGame($game);
        /** @var Collection<int, object{shop_key: string, day: string, amount: string, currency: string}> $own */
        $own = DB::table('price_history')
            ->where('game_id', $game->id)
            ->where('day', '>=', $start->toDateString())
            ->orderBy('day')
            ->get(['shop_key', 'day', 'amount', 'currency']);

        $days = $this->days($changes ?? [], $own, $start, $today, $zone);
        $steamShopId = (int) config('games.chart.steam_shop_id');

        $series = [
            $this->series(self::SERIES_OFFICIAL, null, Platform::Pc, $this->fromChanges($changes ?? [], $days, $currency, $zone)),
            $this->series(self::SERIES_STEAM, null, Platform::Pc, $this->fromChanges(
                array_values(array_filter($changes ?? [], fn (ItadPriceChange $change): bool => $change->shopId === $steamShopId)),
                $days,
                $currency,
                $zone,
            )),
            $this->series(self::SERIES_KEYSHOPS, null, Platform::Pc, $this->fromOwn($own, CurrentPrice::KEYSHOPS_KEY, $days, $currency)),
            $this->series(self::SERIES_KEYSHOPS_LOW, null, Platform::Pc, $this->keyshopsLow($game, $days, $currency)),
        ];

        foreach ($game->storeListings as $listing) {
            $label = $listing->platforms === null ? $listing->name : $listing->name.' ('.$listing->platforms.')';
            $series[] = $this->series($listing->shopKey(), $label, Platform::PlayStation, $this->fromOwn($own, $listing->shopKey(), $days, $currency));
        }

        $target = $item?->target();
        $convertedTarget = $target === null ? null : $this->rates->convert($target, $currency);

        return [
            'days' => $days,
            // Jen řady, které mají aspoň jednu hodnotu
            'series' => array_values(array_filter($series, fn (array $item): bool => array_filter($item['data'], fn (?float $value): bool => $value !== null) !== [])),
            'target' => $convertedTarget === null ? null : $convertedTarget->cents / Money::SUBUNITS,
            'currency' => $currency,
            'itadAvailable' => $changes !== null,
            // Od kdy máme vlastní historii (obchody s klíči, PS Store) — poznámka pod grafem
            'ownSince' => $own->isEmpty() ? null : (string) $own->first()->day,
        ];
    }

    /**
     * Dny grafu od prvního záznamu (ITAD nebo vlastní historie) do dneška.
     *
     * @param  list<ItadPriceChange>  $changes
     * @param  Collection<int, object{shop_key: string, day: string, amount: string, currency: string}>  $own
     * @return list<string>
     */
    private function days(array $changes, Collection $own, CarbonImmutable $start, CarbonImmutable $today, string $zone): array
    {
        $first = null;
        foreach ($changes as $change) {
            $day = $change->at->setTimezone($zone)->startOfDay();
            $first = $first === null || $day->lessThan($first) ? $day : $first;
        }

        $firstOwn = $own->first();
        if ($firstOwn !== null) {
            $day = CarbonImmutable::parse($firstOwn->day, $zone);
            $first = $first === null || $day->lessThan($first) ? $day : $first;
        }

        if ($first === null) {
            return [];
        }

        $days = [];
        for ($day = $first->greaterThan($start) ? $first : $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * Denní nejnižší cena napříč obchody ze změn ITAD — cena obchodu platí od
     * změny do další; den bere nejnižší cenu, která v něm platila.
     *
     * @param  list<ItadPriceChange>  $changes
     * @param  list<string>  $days
     * @return list<float|null>
     */
    private function fromChanges(array $changes, array $days, string $currency, string $zone): array
    {
        // Změny podle dne a obchodu, v měně zobrazení (převod jednou na změnu)
        $byDay = [];
        foreach ($changes as $change) {
            $converted = $this->rates->convert($change->price, $currency);
            if ($converted !== null) {
                $byDay[$change->at->setTimezone($zone)->toDateString()][] = [$change->shopId, $converted->cents, $change->at->getTimestamp()];
            }
        }

        $current = [];
        $data = [];
        foreach ($days as $day) {
            $dayLows = $current;
            $todayChanges = $byDay[$day] ?? [];
            usort($todayChanges, fn (array $a, array $b): int => $a[2] <=> $b[2]);
            foreach ($todayChanges as [$shopId, $cents]) {
                $dayLows[$shopId] = isset($dayLows[$shopId]) ? min($dayLows[$shopId], $cents) : $cents;
                $current[$shopId] = $cents;
            }

            $data[] = $dayLows === [] ? null : min($dayLows) / Money::SUBUNITS;
        }

        return $data;
    }

    /**
     * Denní řada z vlastní historie obchodu; den bez záznamu = null (mezera).
     *
     * @param  Collection<int, object{shop_key: string, day: string, amount: string, currency: string}>  $own
     * @param  list<string>  $days
     * @return list<float|null>
     */
    private function fromOwn(Collection $own, string $shopKey, array $days, string $currency): array
    {
        $byDay = [];
        foreach ($own->where('shop_key', $shopKey) as $row) {
            $converted = $this->rates->convert(Money::fromDecimal((string) $row->amount, (string) $row->currency), $currency);
            if ($converted !== null) {
                $byDay[(string) $row->day] = $converted->cents / Money::SUBUNITS;
            }
        }

        return array_map(fn (string $day): ?float => $byDay[$day] ?? null, $days);
    }

    /**
     * Historické minimum obchodů s klíči (gg.deals) jako stejná hodnota každý
     * den — srovnání pro krátkou vlastní historii klíčů; bez minima prázdná řada.
     *
     * @param  list<string>  $days
     * @return list<float|null>
     */
    private function keyshopsLow(Game $game, array $days, string $currency): array
    {
        $low = $game->loadMissing('priceLows')->priceLows->firstWhere('scope', PriceScope::Keyshops);
        $converted = $low instanceof PriceLow ? $this->rates->convert($low->price(), $currency) : null;

        return array_fill(0, count($days), $converted === null ? null : $converted->cents / Money::SUBUNITS);
    }

    /**
     * Řada grafu.
     *
     * @param  list<float|null>  $data
     * @return array{key: string, name: string|null, platform: string, data: list<float|null>}
     */
    private function series(string $key, ?string $name, Platform $platform, array $data): array
    {
        return ['key' => $key, 'name' => $name, 'platform' => $platform->value, 'data' => $data];
    }
}
