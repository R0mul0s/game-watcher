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
use App\Domain\Catalog\Actions\RefreshGameDetails;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Metacritic\Actions\RefreshMetascores;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\User;
use Carbon\CarbonImmutable;

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
        private readonly RefreshGameDetails $refreshGameDetails,
        private readonly RefreshMetascores $refreshMetascores,
    ) {}

    /**
     * Sledovaná hra. Zadaná platforma (R24) se nastaví i u hry, kterou už
     * uživatel sleduje; bez ní zůstane, jak byla.
     *
     * @throws GameNotFound
     * @throws ItadUnavailable
     */
    public function handle(User $user, string $itadId, ?Platform $platform = null): Game
    {
        $game = Game::query()->where('itad_id', $itadId)->first()
            ?? $this->importGame->handle($itadId)
            ?? throw new GameNotFound($itadId);

        // Vydaná hra už na vydání ani první slevu neupozorní (R41)
        $now = CarbonImmutable::now();
        $item = $user->watchlistItems()->firstOrCreate(['game_id' => $game->id], $game->isReleased()
            ? ['release_notified_at' => $now, 'first_sale_notified_at' => $now]
            : []);
        if ($platform !== null && $item->platform !== $platform) {
            $item->update(['platform' => $platform]);
        }

        if ($game->currentPrices()->doesntExist()) {
            $this->refreshPrices->handle(collect([$game]));
        }

        // Žánry a režimy ze Steamu pro filtry (R25) — výpadek přidání nezastaví, doplní je cron
        if ($game->steam_app_id !== null && $game->steam_details_at === null) {
            $this->refreshGameDetails->handle(collect([$game]));
        }

        // Metascore z Metacritic pro hru, které ho ITAD nedává (R33) — výpadek
        // přidání nezastaví, doplní ho cron
        if ($game->metacritic_checked_at === null && $game->itadMetascore() === null) {
            $this->refreshMetascores->handle(collect([$game]));
        }

        return $game;
    }
}
