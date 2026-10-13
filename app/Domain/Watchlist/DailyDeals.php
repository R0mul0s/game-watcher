<?php

/**
 * Slevy dne — sledované hry, které zlevnily od včerejška.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Watchlist;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceBoard;
use App\Domain\Pricing\PriceFilter;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sekce nad přehledem (R26): nejlevnější nabídka hlídané platformy teď
 * (včetně obchodů s klíči, bez ceny PS Plus — historie ji nezná) proti
 * nejnižší ceně včerejška z historie cen. Historie je po dnech (místní den),
 * proto „od včerejška“, ne přesně 24 h. Hra bez včerejšího záznamu (právě
 * přidaná) se nepočítá, stejně jako pokles pod alerts.daily_deals_min_drop
 * (posun ceny v eurech o haléře).
 */
final class DailyDeals
{
    private const PERCENT = 100;

    /** Slevy dne porovnávají se včerejškem. */
    private const DAILY_DAYS = 1;

    public function __construct(
        private readonly PriceBoard $priceBoard,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Hry, které zlevnily, od největšího poklesu — nejvýš alerts.daily_deals_limit.
     * Hry musí mít načtené vazby currentPrices a priceLows.
     *
     * @param  Collection<int, Game>  $games
     * @param  Collection<int, Platform|null>  $watchPlatforms  hlídaná platforma podle ID hry
     * @return list<array{slug: string, title: string, url: string, bannerUrl: string|null, price: array{amount: float, currency: string}, previous: array{amount: float, currency: string}, drop: int}>
     */
    public function handle(Collection $games, Collection $watchPlatforms, string $currency): array
    {
        $drops = array_slice($this->drops($games, $watchPlatforms, $currency, self::DAILY_DAYS), 0, (int) config('games.alerts.daily_deals_limit'));

        return array_map(fn (PriceDrop $drop): array => [
            'slug' => $drop->game->slug,
            'title' => $drop->game->title,
            'url' => route('games.show', $drop->game, absolute: false),
            'bannerUrl' => $drop->game->banner_url ?? $drop->game->boxart_url,
            'price' => $drop->price->toArray(),
            'previous' => $drop->previous->toArray(),
            'drop' => $drop->drop,
        ], $drops);
    }

    /**
     * Hry, jejichž nejlevnější nabídka teď je nižší než nejnižší cena dne
     * před $days dny — od největšího poklesu. Slevy dne (1 den) i týdenní
     * souhrn (R36). Hry musí mít načtené vazby currentPrices a priceLows.
     *
     * @param  Collection<int, Game>  $games
     * @param  Collection<int, Platform|null>  $watchPlatforms  hlídaná platforma podle ID hry
     * @return list<PriceDrop>
     */
    public function drops(Collection $games, Collection $watchPlatforms, string $currency, int $days): array
    {
        $day = CarbonImmutable::now((string) config('games.display_timezone'))->subDays($days)->toDateString();
        $history = DB::table('price_history')
            ->whereIn('game_id', $games->pluck('id'))
            ->where('day', $day)
            ->get(['game_id', 'shop_key', 'amount', 'currency'])
            ->groupBy('game_id');

        $drops = [];
        foreach ($games as $game) {
            $filter = (new PriceFilter)->restrictedTo($watchPlatforms->get($game->id));
            $offer = $this->priceBoard->cheapest($this->priceBoard->summary($game, $currency, $filter));
            $previous = $this->previousLow($history->get($game->id) ?? collect(), $filter, $currency);

            if ($offer === null || $previous === null || $offer['effectiveCents'] >= $previous->cents) {
                continue;
            }

            $drop = (int) round(($previous->cents - $offer['effectiveCents']) * self::PERCENT / $previous->cents);
            if ($drop >= (int) config('games.alerts.daily_deals_min_drop')) {
                $drops[] = new PriceDrop($game, new Money((int) $offer['effectiveCents'], $currency), $previous, $drop);
            }
        }

        usort($drops, fn (PriceDrop $a, PriceDrop $b): int => $b->drop <=> $a->drop);

        return $drops;
    }

    /**
     * Nejnižší včerejší cena obchodů, které filtr pouští, v měně zobrazení.
     *
     * @param  Collection<int, object{shop_key: string, amount: string, currency: string}>  $rows
     */
    private function previousLow(Collection $rows, PriceFilter $filter, string $currency): ?Money
    {
        $low = null;
        foreach ($rows as $row) {
            $platform = str_starts_with((string) $row->shop_key, StoreListing::PLAYSTATION_KEY_PREFIX) ? Platform::PlayStation : Platform::Pc;
            if (! $filter->accepts($platform)) {
                continue;
            }

            $converted = $this->rates->convert(Money::fromDecimal((string) $row->amount, (string) $row->currency), $currency);
            if ($converted !== null && ($low === null || $converted->cents < $low->cents)) {
                $low = $converted;
            }
        }

        return $low;
    }
}
