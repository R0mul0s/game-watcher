<?php

/**
 * Game Pass u sledovaných her z IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Itad\ItadSubscription;
use App\Models\Game;

/**
 * Krok cronu (R50): jeden dávkový dotaz games/subs/v1 na games.prices.itad_batch_size
 * sledovaných her. Hlídá se jen Game Pass (games.sources.itad.game_pass_id);
 * hra, kterou odpověď nezmiňuje, v žádném předplatném není.
 */
final class RefreshSubscriptions
{
    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Obnoví Game Pass sledovaných her; vrátí počet her v Game Passu.
     *
     * @throws ItadUnavailable
     */
    public function handle(): int
    {
        $gamePassId = (int) config('games.sources.itad.game_pass_id');
        $inGamePass = 0;

        $games = Game::query()->whereHas('watchlistItems')->orderBy('id')->get();
        foreach ($games->chunk((int) config('games.prices.itad_batch_size')) as $batch) {
            $subscriptions = $this->itad->subscriptions(array_values($batch->map(fn (Game $game): string => $game->itad_id)->all()));

            foreach ($batch as $game) {
                $gamePass = collect($subscriptions[$game->itad_id] ?? [])
                    ->first(fn (ItadSubscription $subscription): bool => $subscription->id === $gamePassId);

                $game->update([
                    'in_game_pass' => $gamePass !== null,
                    'game_pass_leaves_at' => $gamePass?->leavesAt,
                ]);
                $inGamePass += $gamePass !== null ? 1 : 0;
            }
        }

        return $inGamePass;
    }
}
