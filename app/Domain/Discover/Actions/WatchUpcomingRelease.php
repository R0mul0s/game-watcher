<?php

/**
 * Sledování nadcházející hry ze záložky Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Discover\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Watchlist\Actions\WatchGame;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\UpcomingRelease;
use App\Models\User;

/**
 * Hra z IGDB se s katalogem spáruje až teď (R46): podle Steam App ID přesně,
 * hra bez Steamu (exkluzivita PS5) nebo se Steam App ID, které ITAD nezná,
 * podle přesného názvu jako v hledání (R22).
 * ITAD ID se uloží k řádku, další sledování už ITAD nevolá. Hra vycházející
 * jen na PS5 se hlídá jen na PlayStationu (R24) — jinak všude.
 */
final class WatchUpcomingRelease
{
    public function __construct(
        private readonly ItadClient $itad,
        private readonly WatchGame $watchGame,
    ) {}

    /**
     * Sledovaná hra z katalogu.
     *
     * @throws GameNotFound ITAD hru nezná
     * @throws ItadUnavailable
     */
    public function handle(User $user, UpcomingRelease $release): Game
    {
        $itadId = $release->itad_id ?? $this->pair($release);
        $onlyPlayStation = $release->releaseOn(Platform::PlayStation) !== null && $release->releaseOn(Platform::Pc) === null;

        return $this->watchGame->handle($user, $itadId, $onlyPlayStation ? Platform::PlayStation : null);
    }

    /**
     * Najde hru v ITAD a uloží její ID k řádku.
     *
     * @throws GameNotFound
     * @throws ItadUnavailable
     */
    private function pair(UpcomingRelease $release): string
    {
        $game = ($release->steam_app_id === null ? null : $this->itad->lookupSteamApp($release->steam_app_id))
            ?? $this->itad->lookupTitle($release->title);
        if ($game === null) {
            throw new GameNotFound($release->title);
        }

        $release->update(['itad_id' => $game->id]);

        return $game->id;
    }
}
