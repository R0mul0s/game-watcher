<?php

/**
 * Odebrání hry z osobního seznamu sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Watchlist\Actions;

use App\Models\Game;
use App\Models\User;

/**
 * Hra zůstává v katalogu — může ji sledovat někdo jiný a bude mít historii cen (R11).
 */
final class UnwatchGame
{
    /**
     * Odebere hru ze seznamu uživatele; nesledovanou hru přejde bez chyby.
     */
    public function handle(User $user, Game $game): void
    {
        $user->watchlistItems()->where('game_id', $game->id)->delete();
    }
}
