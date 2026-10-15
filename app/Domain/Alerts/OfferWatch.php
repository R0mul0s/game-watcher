<?php

/**
 * Upozornění na Game Pass a bundly sledované hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Currency\ExchangeRates;
use App\Enums\AlertReason;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\GameBundle;
use App\Models\User;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;

/**
 * Pravidla — doplňují upozornění na cenu (PriceWatch) a vydání (ReleaseWatch),
 * mají přednost ona; za běh nejvýš jedno upozornění na hru, další počká:
 * - Game Pass (R50): hra je v Game Passu a účet ho má — jednou; když hra
 *   z Game Passu odejde, stav se smaže a příští návrat upozorní znovu;
 * - bundle (R51): hra je v aktivním bundlu, na který upozornění ještě
 *   neodešlo — nejlevnější takový bundle; cena v měně účtu.
 *
 * Hra hlídaná jen na PlayStationu (R24) ani jedno nedostane — Game Pass
 * i bundly jsou pro PC. Hra musí mít načtenou vazbu bundles.
 */
final class OfferWatch
{
    public function __construct(private readonly ExchangeRates $rates) {}

    /**
     * Upozornění na Game Pass nebo bundle; null = nic nového.
     */
    public function check(User $user, WatchlistItem $item, Game $game): ?PriceAlert
    {
        if (! $game->in_game_pass && $item->game_pass_notified_at !== null) {
            $item->update(['game_pass_notified_at' => null]);
        }

        if ($item->platform === Platform::PlayStation) {
            return null;
        }

        if ($user->has_game_pass && $game->in_game_pass && $item->game_pass_notified_at === null) {
            return new PriceAlert(
                item: $item,
                game: $game,
                reason: AlertReason::GamePass,
                price: null,
                shop: (string) __('app.alerts.game_pass'),
                url: null,
                target: $item->target(),
                until: $game->game_pass_leaves_at,
            );
        }

        $notified = $item->notified_bundles ?? [];
        $bundle = $game->bundles
            ->reject(fn (GameBundle $bundle): bool => in_array($bundle->bundle_id, $notified, true))
            // Bez ceny („Build your own“) až za bundly s cenou
            ->sortBy(fn (GameBundle $bundle): array => [$bundle->price() === null ? 1 : 0, (float) $bundle->amount])
            ->first();

        if (! $bundle instanceof GameBundle) {
            return null;
        }

        $price = $bundle->price();

        return new PriceAlert(
            item: $item,
            game: $game,
            reason: AlertReason::Bundle,
            price: $price === null ? null : ($this->rates->convert($price, $user->displayCurrency()) ?? $price),
            shop: $bundle->shop,
            url: $bundle->url,
            target: $item->target(),
            bundle: $bundle,
            until: $bundle->expires_at,
        );
    }

    /**
     * Po zápisu do centra (R53) zapamatuje Game Pass nebo bundle; seznam bundlů zúží na
     * aktivní (prošlé ID by jen rostly).
     */
    public function markNotified(PriceAlert $alert, CarbonImmutable $now): void
    {
        if ($alert->reason === AlertReason::GamePass) {
            $alert->item->update(['game_pass_notified_at' => $now]);

            return;
        }

        if ($alert->reason === AlertReason::Bundle && $alert->bundle !== null) {
            $active = $alert->game->bundles->pluck('bundle_id')->all();
            $notified = array_values(array_intersect($alert->item->notified_bundles ?? [], $active));
            $notified[] = $alert->bundle->bundle_id;

            $alert->item->update(['notified_bundles' => array_values(array_unique($notified))]);
        }
    }
}
