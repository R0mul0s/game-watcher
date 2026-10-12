<?php

/**
 * Přidání hry na osobní seznam sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Watchlist\Actions;

use App\Domain\Catalog\Actions\ImportGame;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Models\Game;
use App\Models\User;

/**
 * Hru, kterou katalog ještě nemá, nejdřív převezme z ITAD; hru z katalogu
 * jen přidá na seznam (katalog je společný, R11). Opakované přidání nic nezdvojí.
 * Hra bez cen dostane ceny hned (dva požadavky na API) — jinak by na ně
 * uživatel čekal do dalšího běhu cronu; výpadek zdroje přidání nezastaví.
 */
final class WatchGame
{
    public function __construct(
        private readonly ImportGame $importGame,
        private readonly RefreshPrices $refreshPrices,
    ) {}

    /**
     * Sledovaná hra.
     *
     * @throws GameNotFound
     * @throws ItadUnavailable
     */
    public function handle(User $user, string $itadId): Game
    {
        $game = Game::query()->where('itad_id', $itadId)->first()
            ?? $this->importGame->handle($itadId)
            ?? throw new GameNotFound($itadId);

        $user->watchlistItems()->firstOrCreate(['game_id' => $game->id]);

        if ($game->currentPrices()->doesntExist()) {
            $this->refreshPrices->handle(collect([$game]));
        }

        return $game;
    }
}
