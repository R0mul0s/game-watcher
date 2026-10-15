<?php

/**
 * Upozornění na vydání a první slevu hry sledované před vydáním.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceBoard;
use App\Domain\Pricing\PriceFilter;
use App\Enums\AlertReason;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\User;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;

/**
 * Pravidla (R41) — doplňují upozornění na cenu (PriceWatch), mají přednost
 * ona; každé přijde nejvýš jednou:
 * - „právě vyšlo“: datum vydání nastalo před nejvýš alerts.release_alert_days
 *   dny — s nejlevnější nabídkou hlídané platformy (včetně klíčů), hra bez
 *   nabídky počká, až nějakou mít bude;
 * - „první sleva od vydání“: nejlevnější oficiální nabídka hlídané platformy
 *   je ve slevě a hra vyšla před nejvýš alerts.first_sale_window_days dny —
 *   i nad cílovou cenou.
 *
 * Hra sledovaná až po vydání má stav vyplněný už od přidání (WatchGame).
 * Hra musí mít načtené vazby currentPrices a priceLows.
 */
final class ReleaseWatch
{
    public function __construct(private readonly PriceBoard $priceBoard) {}

    /**
     * Upozornění na vydání nebo první slevu; null = nic nového.
     */
    public function check(User $user, WatchlistItem $item, Game $game): ?PriceAlert
    {
        $releasePending = $this->releasePending($item, $game);
        $firstSalePending = $this->firstSalePending($item, $game);
        if (! $releasePending && ! $firstSalePending) {
            return null;
        }

        $currency = $user->displayCurrency();
        $summary = $this->priceBoard->summary($game, $currency, (new PriceFilter(null, $user->has_ps_plus))->restrictedTo($item->platform));
        $best = $summary['best'];

        if ($firstSalePending && $best !== null && $best['cut'] > 0) {
            return $this->alert($item, $game, AlertReason::FirstSale, $best, $currency);
        }

        $cheapest = $this->priceBoard->cheapest($summary);

        return $releasePending && $cheapest !== null
            ? $this->alert($item, $game, AlertReason::Released, $cheapest, $currency)
            : null;
    }

    /**
     * Po upozornění na hru (jakémkoli, zapsaném do centra) označí vydání a první slevu
     * jako oznámené, když už nastaly — uživatel cenu právě dostal.
     */
    public function markCovered(PriceAlert $alert, CarbonImmutable $now): void
    {
        if (! $alert->game->isReleased()) {
            return;
        }

        $alert->item->update([
            'release_notified_at' => $alert->item->release_notified_at ?? $now,
            'first_sale_notified_at' => $alert->item->first_sale_notified_at ?? ($alert->discounted ? $now : null),
        ]);
    }

    /**
     * Hra vyšla nedávno a upozornění na vydání ještě neodešlo?
     */
    private function releasePending(WatchlistItem $item, Game $game): bool
    {
        return $item->release_notified_at === null
            && $this->releasedWithin($game, (int) config('games.alerts.release_alert_days'));
    }

    /**
     * Hra vyšla v okně první slevy a upozornění na ni ještě neodešlo?
     */
    private function firstSalePending(WatchlistItem $item, Game $game): bool
    {
        return $item->first_sale_notified_at === null
            && $this->releasedWithin($game, (int) config('games.alerts.first_sale_window_days'));
    }

    /**
     * Vyšla hra (podle místního dne) nejvýš před tolika dny?
     */
    private function releasedWithin(Game $game, int $days): bool
    {
        return $game->isReleased()
            && $game->release_date?->toDateString() >= Game::today()->subDays($days)->toDateString();
    }

    /**
     * Upozornění s nabídkou souhrnu PriceBoard.
     *
     * @param  array<string, mixed>  $offer
     */
    private function alert(WatchlistItem $item, Game $game, AlertReason $reason, array $offer, string $currency): PriceAlert
    {
        return new PriceAlert(
            item: $item,
            game: $game,
            reason: $reason,
            price: new Money((int) $offer['effectiveCents'], $currency),
            shop: (string) ($offer['shop'] ?? __('app.ui.prices.keyshops')),
            url: is_string($offer['url'] ?? null) ? $offer['url'] : null,
            target: $item->target(),
            keyshops: ($offer['shopKey'] ?? null) === CurrentPrice::KEYSHOPS_KEY,
            discounted: ($offer['cut'] ?? 0) > 0,
        );
    }
}
