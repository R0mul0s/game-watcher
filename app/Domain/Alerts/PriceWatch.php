<?php

/**
 * Vyhodnocení ceny sledované hry pro upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceBoard;
use App\Domain\Pricing\PriceFilter;
use App\Enums\AlertReason;
use App\Enums\PriceScope;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;
use App\Models\User;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pravidla upozornění (R26):
 * - cena = nejlevnější nabídka hlídané platformy (R24) včetně obchodů s klíči,
 *   s PS Plus u účtu cena pro předplatitele, v měně zobrazení účtu;
 * - s cílovou cenou: cena je na cíli nebo pod ním;
 * - bez cílové ceny: nabídka je ve slevě a na historickém minimu svého zdroje
 *   (oficiální obchody a obchody s klíči z ITAD a gg.deals, PS Store z vlastní
 *   historie, když je aspoň alerts.min_history_days dní dlouhá);
 * - stejnou nebo vyšší cenu podruhé neposílat — znovu až nižší cena, nebo
 *   když podmínka mezitím přestala platit.
 *
 * Hra musí mít načtené vazby currentPrices a priceLows.
 */
final class PriceWatch
{
    public function __construct(
        private readonly PriceBoard $priceBoard,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Platí podmínka upozornění a je cena nová (upozornění poslat)?
     */
    public function check(User $user, WatchlistItem $item, Game $game): PriceCheck
    {
        $currency = $user->displayCurrency();
        $filter = (new PriceFilter(null, $user->has_ps_plus))->restrictedTo($item->platform);
        $summary = $this->priceBoard->summary($game, $currency, $filter);

        $offer = $this->priceBoard->cheapest($summary);
        if ($offer === null) {
            return new PriceCheck(false, null);
        }

        $price = new Money((int) $offer['effectiveCents'], $currency);
        $target = $item->target();
        $met = $target === null
            ? $this->isHistoricalLow($game, $offer)
            : ($converted = $this->rates->convert($target, $currency)) !== null && $price->cents <= $converted->cents;

        if (! $met || ! $this->isNew($item, $price)) {
            return new PriceCheck($met, null);
        }

        return new PriceCheck(true, new PriceAlert(
            item: $item,
            game: $game,
            reason: $target === null ? AlertReason::HistoricalLow : AlertReason::Target,
            price: $price,
            shop: (string) ($offer['shop'] ?? __('app.ui.prices.keyshops')),
            url: is_string($offer['url'] ?? null) ? $offer['url'] : null,
            target: $target,
            keyshops: $offer['shopKey'] === CurrentPrice::KEYSHOPS_KEY,
        ));
    }

    /**
     * Je nabídka ve slevě a na historickém minimu svého zdroje?
     *
     * @param  array<string, mixed>  $offer
     */
    private function isHistoricalLow(Game $game, array $offer): bool
    {
        $current = $game->currentPrices->firstWhere('shop_key', $offer['shopKey']);
        if (! $current instanceof CurrentPrice) {
            return false;
        }

        $low = match ($current->source) {
            PriceSource::Itad => $current->cut > 0 ? $this->sourceLow($game, PriceScope::Official) : null,
            // Obchody s klíči slevu neuvádějí — rozhoduje jen minimum
            PriceSource::GgDeals => $this->sourceLow($game, PriceScope::Keyshops),
            PriceSource::PlayStation => $current->cut > 0 ? $this->ownHistoryLow($current) : null,
        };

        return $low !== null && $this->atMost($current->price(), $low);
    }

    /**
     * Historické minimum ze zdroje (ITAD, gg.deals).
     */
    private function sourceLow(Game $game, PriceScope $scope): ?Money
    {
        $low = $game->priceLows->firstWhere('scope', $scope);

        return $low instanceof PriceLow ? $low->price() : null;
    }

    /**
     * Nejnižší cena z vlastní historie produktu před dneškem — jen když je
     * historie dost dlouhá, jinak by „minimum“ byla první stažená cena.
     */
    private function ownHistoryLow(CurrentPrice $current): ?Money
    {
        $today = CarbonImmutable::now((string) config('games.display_timezone'))->toDateString();
        $history = DB::table('price_history')
            ->where('game_id', $current->game_id)
            ->where('shop_key', $current->shop_key)
            ->where('day', '<', $today)
            ->selectRaw('MIN(day) AS first_day, MIN(amount) AS low')
            ->first();

        $firstDay = $history === null ? null : $history->first_day;
        $low = $history === null ? null : $history->low;
        $long = is_string($firstDay)
            && CarbonImmutable::parse($firstDay)->diffInDays($today) >= (int) config('games.alerts.min_history_days');

        return $long && is_string($low) ? Money::fromDecimal($low, $current->currency) : null;
    }

    /**
     * Je částka nejvýš srovnávaná (v její měně)? Bez kurzu nelze rozhodnout.
     */
    private function atMost(Money $amount, Money $limit): bool
    {
        $converted = $this->rates->convert($amount, $limit->currency);

        return $converted !== null && $converted->cents <= $limit->cents;
    }

    /**
     * Upozornění na tuhle cenu ještě neodešlo — dosud žádné, v jiné měně,
     * nebo byla poslední upozorněná cena vyšší.
     */
    private function isNew(WatchlistItem $item, Money $price): bool
    {
        $notified = $item->notifiedPrice();

        return $notified === null || $notified->currency !== $price->currency || $price->cents < $notified->cents;
    }
}
