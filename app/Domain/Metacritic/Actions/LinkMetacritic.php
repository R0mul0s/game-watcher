<?php

/**
 * Připojení stránky hry na Metacritic odkazem od uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic\Actions;

use App\Domain\Metacritic\Exceptions\MetacriticGameNotFound;
use App\Domain\Metacritic\Exceptions\MetacriticUnavailable;
use App\Domain\Metacritic\MetacriticClient;
use App\Domain\Metacritic\MetacriticGame;
use App\Domain\Metacritic\MetacriticUrl;
use App\Models\Game;

/**
 * Když slug odvozený z názvu nesedí (Alan Wake 2 je na Metacritic alan-wake-ii),
 * uživatel vloží odkaz na detailu hry (R33). Odkaz platí i pro hru s Metascore
 * z ITAD — ten má ale na kartě přednost (Game::metascore()).
 */
final class LinkMetacritic
{
    public function __construct(
        private readonly MetacriticClient $client,
        private readonly RefreshMetascores $refreshMetascores,
    ) {}

    /**
     * Připojí stránku a uloží její Metascore.
     *
     * @throws MetacriticGameNotFound
     * @throws MetacriticUnavailable
     */
    public function handle(Game $game, string $url): MetacriticGame
    {
        $slug = MetacriticUrl::parse($url) ?? throw new MetacriticGameNotFound($url);
        $page = $this->client->game($slug) ?? throw new MetacriticGameNotFound($url);

        $this->refreshMetascores->store($game, $slug, $page);

        return $page;
    }
}
