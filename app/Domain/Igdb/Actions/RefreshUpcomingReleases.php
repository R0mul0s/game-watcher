<?php

/**
 * Obnova seznamu nadcházejících her z IGDB.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Igdb\Actions;

use App\Domain\Igdb\Exceptions\IgdbUnavailable;
use App\Domain\Igdb\IgdbClient;
use App\Domain\Igdb\IgdbGame;
use App\Enums\Platform;
use App\Models\UpcomingRelease;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Krok cronu (R46): jeden dotaz na IGDB a seznam se přepíše — nové hry přibudou,
 * hry, které vyšly nebo z dotazu vypadly, se smažou. ITAD ID spárovaných her
 * zůstává. Prázdná odpověď je podezřelá (běžně ~250 her) — seznam se pak nemaže.
 */
final class RefreshUpcomingReleases
{
    /** Řádků v jednom hromadném zápisu. */
    private const UPSERT_CHUNK = 100;

    /** Sloupce, které obnova přepisuje (ne ITAD ID ani čas vzniku). */
    private const UPDATED_COLUMNS = [
        'title', 'igdb_url', 'hypes', 'steam_app_id', 'cover_image_id', 'banner_image_id', 'genres', 'coop',
        'pc_release_on', 'pc_release_precision', 'pc_early_access',
        'playstation_release_on', 'playstation_release_precision', 'playstation_early_access',
        'next_release_on', 'updated_at',
    ];

    public function __construct(private readonly IgdbClient $igdb) {}

    /**
     * Je IGDB nastavené? Bez klíče aplikace se krok přeskočí.
     */
    public function isEnabled(): bool
    {
        return $this->igdb->isConfigured();
    }

    /**
     * Stáhne a uloží seznam; vrátí počet nadcházejících her.
     *
     * @throws IgdbUnavailable
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $games = array_values(array_filter($this->igdb->upcoming($now), fn (IgdbGame $game): bool => $game->releases !== []));
        if ($games === []) {
            throw new IgdbUnavailable((string) __('app.monitoring.igdb_empty'));
        }

        $rows = array_map(fn (IgdbGame $game): array => $this->row($game, $now), $games);

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                UpcomingRelease::query()->upsert($chunk, ['igdb_id'], self::UPDATED_COLUMNS);
            }
            UpcomingRelease::query()->whereNotIn('igdb_id', array_column($rows, 'igdb_id'))->delete();
        });

        return count($rows);
    }

    /**
     * Řádek tabulky pro hromadný zápis — hodnoty už v podobě pro databázi
     * (upsert nepoužívá přetypování modelu).
     *
     * @return array<string, mixed>
     */
    private function row(IgdbGame $game, CarbonImmutable $now): array
    {
        $row = [
            'igdb_id' => $game->igdbId,
            'title' => $game->title,
            'igdb_url' => $game->url,
            'hypes' => $game->hypes,
            'steam_app_id' => $game->steamAppId,
            'cover_image_id' => $game->coverImageId,
            'banner_image_id' => $game->bannerImageId,
            'genres' => json_encode($game->genres, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'coop' => $game->coop,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $next = null;
        foreach (Platform::cases() as $platform) {
            $release = $game->releases[$platform->value] ?? null;
            $row[$platform->value.'_release_on'] = $release === null ? null : $release['date']->toDateString();
            $row[$platform->value.'_release_precision'] = $release === null ? null : $release['precision']->value;
            $row[$platform->value.'_early_access'] = $release !== null && $release['earlyAccess'];
            if ($release !== null && ($next === null || $release['date']->lessThan($next))) {
                $next = $release['date'];
            }
        }

        // Hra bez vydání se sem nedostane (filtr v handle())
        $row['next_release_on'] = $next?->toDateString();

        return $row;
    }
}
