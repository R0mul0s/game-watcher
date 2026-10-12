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
     * @param  int|null  $limit  nejvýš výsledků hledání podle názvu; null = games.catalog.search_results
     * @return list<ItadGame>
     *
     * @throws ItadUnavailable
     */
    public function handle(GameQuery $query, ?int $limit = null): array
    {
        $limit ??= (int) config('games.catalog.search_results');
        $key = self::CACHE_PREFIX.$query->cacheKey().':'.$limit;

        /** @var list<array{id: string, slug: string, title: string, type: string, mature: bool, boxartUrl: ?string, bannerUrl: ?string, thumbnailUrl: ?string}>|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_map(ItadGame::fromArray(...), $cached);
        }

        if ($query->steamAppId !== null) {
            $game = $this->itad->lookupSteamApp($query->steamAppId);
            $games = $game === null ? [] : [$game];
        } else {
            $games = $this->searchTitle((string) $query->title, $limit);
        }

        // Odkaz na ITAD (R30) — hra s přesně tímto slugem, jinak výsledky podle názvu
        if ($query->itadSlug !== null) {
            $exact = array_values(array_filter($games, fn (ItadGame $game): bool => $game->slug === $query->itadSlug));
            $games = $exact === [] ? $games : $exact;
        }

        Cache::put(
            $key,
            array_map(fn (ItadGame $game): array => $game->toArray(), $games),
            (int) config('games.catalog.search_cache_seconds'),
        );

        return $games;
    }

    /**
     * Hledání podle názvu doplněné o hru s přesně tímto názvem na prvním místě —
     * `games/search` exkluzivity konzolí bez obchodů vynechává (Saros, Ghost of
     * Yōtei, R22).
     *
     * @return list<ItadGame>
     *
     * @throws ItadUnavailable
     */
    private function searchTitle(string $title, int $limit): array
    {
        $games = $this->itad->search($title, $limit);
        $exact = $this->itad->lookupTitle($title);
        if ($exact === null) {
            return $games;
        }

        return [$exact, ...array_values(array_filter($games, fn (ItadGame $game): bool => $game->id !== $exact->id))];
    }
}
