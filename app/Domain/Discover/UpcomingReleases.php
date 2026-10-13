<?php

/**
 * Záložka Nadcházející na stránce Objevit — hry z IGDB, které uživatel nesleduje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Discover;

use App\Enums\Platform;
use App\Enums\UpcomingSort;
use App\Models\Game;
use App\Models\UpcomingRelease;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Čte jen vlastní tabulku upcoming_releases (R46) — IGDB ani ITAD se při
 * zobrazení nevolá. Sledovaná hra (podle ITAD ID nebo Steam App ID) se vynechá;
 * hra, kterou katalog už má, dostane odkaz na detail.
 */
final class UpcomingReleases
{
    /**
     * Hry pro stránku, nabídka žánrů a čas poslední obnovy seznamu.
     *
     * @return array{releases: list<array<string, mixed>>, genres: list<array{name: string, count: int}>, updatedAt: string|null}
     */
    public function build(User $user, UpcomingFilters $filters): array
    {
        $watched = $user->watchedGames()->get(['games.itad_id', 'games.steam_app_id']);
        $watchedItadIds = $watched->pluck('itad_id')->all();
        $watchedSteamIds = $watched->pluck('steam_app_id')->filter()->values()->all();

        $releases = $this->query($filters)
            ->where(fn (Builder $query) => $query->whereNull('itad_id')->orWhereNotIn('itad_id', $watchedItadIds))
            ->where(fn (Builder $query) => $query->whereNull('steam_app_id')->orWhereNotIn('steam_app_id', $watchedSteamIds))
            ->get();

        $slugs = $this->catalogSlugs($releases);
        $updatedAt = UpcomingRelease::query()->max('updated_at');

        return [
            'releases' => array_values($releases->map(fn (UpcomingRelease $release): array => $this->present($release, $slugs))->all()),
            'genres' => $this->genreOptions($filters->genres),
            // Databáze i aplikace jsou v UTC
            'updatedAt' => is_string($updatedAt) ? CarbonImmutable::parse($updatedAt)->toIso8601String() : null,
        ];
    }

    /**
     * Dotaz s filtry a řazením. Při filtru platformy se řadí podle vydání na ní.
     *
     * @return Builder<UpcomingRelease>
     */
    private function query(UpcomingFilters $filters): Builder
    {
        $query = UpcomingRelease::query();

        if ($filters->platform !== null) {
            $query->whereNotNull($filters->platform->value.'_release_on');
        }
        if ($filters->coop) {
            $query->where('coop', true);
        }
        if ($filters->genres !== []) {
            $query->where(function (Builder $query) use ($filters): void {
                foreach ($filters->genres as $genre) {
                    $query->orWhereJsonContains('genres', $genre);
                }
            });
        }

        return $filters->sort === UpcomingSort::Popular
            ? $query->orderByDesc('hypes')->orderBy('next_release_on')
            : $query->orderBy($filters->platform === null ? 'next_release_on' : $filters->platform->value.'_release_on')->orderByDesc('hypes');
    }

    /**
     * Slugy her, které katalog už má (podle ITAD ID nebo Steam App ID).
     *
     * @param  Collection<int, UpcomingRelease>  $releases
     * @return array{itad: array<string, string>, steam: array<int, string>}
     */
    private function catalogSlugs(Collection $releases): array
    {
        $itadIds = $releases->pluck('itad_id')->filter()->values()->all();
        $steamIds = $releases->pluck('steam_app_id')->filter()->values()->all();
        if ($itadIds === [] && $steamIds === []) {
            return ['itad' => [], 'steam' => []];
        }

        $games = Game::query()
            ->whereIn('itad_id', $itadIds)
            ->orWhereIn('steam_app_id', $steamIds)
            ->get(['itad_id', 'steam_app_id', 'slug']);

        return [
            'itad' => $games->pluck('slug', 'itad_id')->all(),
            'steam' => $games->whereNotNull('steam_app_id')->pluck('slug', 'steam_app_id')->all(),
        ];
    }

    /**
     * Nabídka nejčastějších žánrů ze všech nadcházejících her (IGDB jich má přes
     * 20, vzácné by jen zabíraly místo) a vybrané, i když v nabídce nejsou —
     * výběr z adresy jde zrušit.
     *
     * @param  list<string>  $selected
     * @return list<array{name: string, count: int}>
     */
    private function genreOptions(array $selected): array
    {
        $counts = [];
        foreach (UpcomingRelease::query()->pluck('genres') as $genres) {
            foreach (is_array($genres) ? $genres : [] as $genre) {
                $counts[$genre] = ($counts[$genre] ?? 0) + 1;
            }
        }

        arsort($counts);
        $counts = array_slice($counts, 0, (int) config('games.upcoming.genre_suggestions'), true);
        foreach ($selected as $genre) {
            $counts[$genre] ??= 0;
        }

        return array_map(fn (string $name, int $count): array => ['name' => $name, 'count' => $count], array_keys($counts), $counts);
    }

    /**
     * Hra pro stránku — vydání se stejným datem na víc platformách jako jedna položka.
     *
     * @param  array{itad: array<string, string>, steam: array<int, string>}  $slugs
     * @return array<string, mixed>
     */
    private function present(UpcomingRelease $release, array $slugs): array
    {
        $dates = [];
        foreach (Platform::cases() as $platform) {
            $upcoming = $release->releaseOn($platform);
            if ($upcoming === null) {
                continue;
            }

            $key = $upcoming['date']->toDateString().'|'.$upcoming['precision']->value.'|'.(int) $upcoming['earlyAccess'];
            $dates[$key] ??= [
                'platforms' => [],
                'date' => $upcoming['date']->toDateString(),
                'precision' => $upcoming['precision']->value,
                'earlyAccess' => $upcoming['earlyAccess'],
            ];
            $dates[$key]['platforms'][] = $platform->value;
        }

        $slug = ($release->itad_id === null ? null : $slugs['itad'][$release->itad_id] ?? null)
            ?? ($release->steam_app_id === null ? null : $slugs['steam'][$release->steam_app_id] ?? null);

        return [
            'id' => $release->id,
            'title' => $release->title,
            'imageUrl' => $this->imageUrl($release->banner_image_id, 'banner_size') ?? $this->imageUrl($release->cover_image_id, 'cover_size'),
            'genres' => $release->genres ?? [],
            'coop' => $release->coop,
            'releases' => array_values($dates),
            'igdbUrl' => $release->igdb_url,
            'detailUrl' => $slug === null ? null : route('games.show', $slug, absolute: false),
            'watchUrl' => route('discover.upcoming.watch', $release, absolute: false),
        ];
    }

    /**
     * Adresa obrázku na CDN IGDB ve velikosti z konfigurace.
     */
    private function imageUrl(?string $imageId, string $sizeKey): ?string
    {
        return $imageId === null
            ? null
            : sprintf((string) config('games.sources.igdb.image_url'), (string) config('games.sources.igdb.'.$sizeKey), $imageId);
    }
}
