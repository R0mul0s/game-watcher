<?php

/**
 * Vyhledání her v IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\GameQuery;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Itad\ItadGame;
use Illuminate\Support\Facades\Cache;

/**
 * Výsledky drží cache (games.catalog.search_cache_seconds) — ITAD povoluje
 * 100 požadavků za 5 minut a stejné hledání se při procházení opakuje.
 * Neúspěch se necachuje, další pokus jde hned.
 */
final class SearchGames
{
    private const CACHE_PREFIX = 'catalog:search:';

    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Hry odpovídající dotazu.
     *
     * @return list<ItadGame>
     *
     * @throws ItadUnavailable
     */
    public function handle(GameQuery $query): array
    {
        $key = self::CACHE_PREFIX.$query->cacheKey();

        /** @var list<array{id: string, slug: string, title: string, type: string, mature: bool, boxartUrl: ?string, bannerUrl: ?string, thumbnailUrl: ?string}>|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_map(ItadGame::fromArray(...), $cached);
        }

        if ($query->steamAppId !== null) {
            $game = $this->itad->lookupSteamApp($query->steamAppId);
            $games = $game === null ? [] : [$game];
        } else {
            $games = $this->itad->search((string) $query->title, (int) config('games.catalog.search_results'));
        }

        Cache::put(
            $key,
            array_map(fn (ItadGame $game): array => $game->toArray(), $games),
            (int) config('games.catalog.search_cache_seconds'),
        );

        return $games;
    }
}
