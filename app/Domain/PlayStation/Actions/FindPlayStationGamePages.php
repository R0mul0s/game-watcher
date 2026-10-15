<?php

/**
 * Které hry z výsledků hledání jsou na PlayStationu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Actions;

use App\Domain\Itad\ItadGame;
use App\Domain\PlayStation\PlayStationClient;
use App\Domain\PlayStation\PlayStationUrl;
use Illuminate\Support\Facades\Cache;

/**
 * ITAD platformy nezná a hledání v PS Store vykresluje až prohlížeč — hra je
 * na PlayStationu, když má stránku na www.playstation.com s adresou podle
 * názvu (R24). Ověřuje se jen hra (ne DLC ani balíček) dotazem HEAD, výsledek
 * drží cache podle adresy. Jiná adresa stránky (edice v názvu) hru nenajde —
 * pak zbývá vložit odkaz z PS Store. Stránku hry umí připojit
 * FindPlayStationProduct.
 */
final class FindPlayStationGamePages
{
    private const CACHE_PREFIX = 'playstation:game-page:';

    /** Druh hry z ITAD, u kterého se stránka hledá. */
    private const GAME_TYPE = 'game';

    public function __construct(private readonly PlayStationClient $client) {}

    /**
     * Adresa stránky hry na www.playstation.com podle ITAD ID; jen hry, které
     * ji mají.
     *
     * @param  list<ItadGame>  $games
     * @return array<string, string>
     */
    public function handle(array $games): array
    {
        $slugs = $this->slugs($games);
        $exists = $this->exists(array_values(array_unique($slugs)));

        $pages = [];
        foreach ($slugs as $itadId => $slug) {
            if ($exists[$slug] ?? false) {
                $pages[$itadId] = $this->client->gamePageUrl($slug);
            }
        }

        return $pages;
    }

    /**
     * Jen z cache, bez dotazů na playstation.com (R56) — stránky her, které
     * cache zná, a ITAD ID her, u kterých se to teprve musí zjistit.
     *
     * @param  list<ItadGame>  $games
     * @return array{0: array<string, string>, 1: list<string>}
     */
    public function cached(array $games): array
    {
        $pages = [];
        $pending = [];
        foreach ($this->slugs($games) as $itadId => $slug) {
            $known = $this->fromCache($slug);
            if ($known === null) {
                $pending[] = $itadId;
            } elseif ($known) {
                $pages[$itadId] = $this->client->gamePageUrl($slug);
            }
        }

        return [$pages, $pending];
    }

    /**
     * Adresa stránky podle ITAD ID — jen hry (ne DLC ani balíčky) s názvem,
     * ze kterého jde adresa složit.
     *
     * @param  list<ItadGame>  $games
     * @return array<string, string>
     */
    private function slugs(array $games): array
    {
        $slugs = [];
        foreach ($games as $game) {
            $slug = $game->type === self::GAME_TYPE ? PlayStationUrl::gamePageSlug($game->title) : null;
            if ($slug !== null) {
                $slugs[$game->id] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Existence stránky z cache; null = cache neví.
     */
    private function fromCache(string $slug): ?bool
    {
        $cached = Cache::get(self::CACHE_PREFIX.$slug);

        return is_array($cached) && is_bool($cached['exists'] ?? null) ? $cached['exists'] : null;
    }

    /**
     * Existence stránek — z cache, zbytek jedním souběžným dotazem; nezjištěné
     * (výpadek) se do cache neukládá.
     *
     * @param  list<string>  $slugs
     * @return array<string, bool>
     */
    private function exists(array $slugs): array
    {
        $exists = [];
        $unknown = [];
        foreach ($slugs as $slug) {
            $known = $this->fromCache($slug);
            if ($known === null) {
                $unknown[] = $slug;
            } else {
                $exists[$slug] = $known;
            }
        }

        foreach ($this->client->gamePagesExist($unknown) as $slug => $found) {
            if ($found !== null) {
                $exists[$slug] = $found;
                Cache::put(self::CACHE_PREFIX.$slug, ['exists' => $found], (int) config('games.sources.playstation.game_page_cache_seconds'));
            }
        }

        return $exists;
    }
}
