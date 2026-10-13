<?php

/**
 * Nadcházející hra z IGDB.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Igdb;

use App\Enums\Platform;
use App\Enums\ReleasePrecision;
use Carbon\CarbonImmutable;

/**
 * Hra z dotazu `games` (R46). Vydání se počítá po platformách: na platformě,
 * kde hra už vyšla (minulé datum, které není alfa, beta ani předběžný přístup),
 * nadcházející není — Forza Horizon 6 vyšla na PC a na PS5 teprve vyjde.
 * Na platformě se bere nejbližší budoucí datum plného vydání nebo startu
 * předběžného přístupu v okně (27 z 266 her ho 13. 10. 2026 mělo jako jediné datum).
 */
final readonly class IgdbGame
{
    /**
     * @param  list<string>  $genres  žánry IGDB (anglicky, jsou to data)
     * @param  array<string, array{date: CarbonImmutable, precision: ReleasePrecision, earlyAccess: bool}>  $releases  nadcházející vydání podle Platform::value
     */
    public function __construct(
        public int $igdbId,
        public string $title,
        public ?string $url,
        public int $hypes,
        public ?int $steamAppId,
        public ?string $coverImageId,
        public ?string $bannerImageId,
        public array $genres,
        public bool $coop,
        public array $releases,
    ) {}

    /**
     * Hra z položky odpovědi IGDB; null bez ID nebo názvu. Nadcházející vydání
     * jen mezi $now a $until — hra bez nich má prázdné `releases`.
     *
     * @param  array<mixed>  $item
     */
    public static function fromApi(array $item, CarbonImmutable $now, CarbonImmutable $until): ?self
    {
        $id = $item['id'] ?? null;
        $title = $item['name'] ?? null;
        if (! is_int($id) || ! is_string($title) || $title === '') {
            return null;
        }

        $url = $item['url'] ?? null;
        $hypes = $item['hypes'] ?? 0;

        return new self(
            igdbId: $id,
            title: $title,
            url: is_string($url) ? $url : null,
            hypes: is_int($hypes) ? $hypes : 0,
            steamAppId: self::steamAppId($item['external_games'] ?? null),
            coverImageId: self::imageId($item['cover'] ?? null),
            bannerImageId: self::firstImageId($item['artworks'] ?? null) ?? self::firstImageId($item['screenshots'] ?? null),
            genres: self::genres($item['genres'] ?? null),
            coop: self::isCoop($item),
            releases: self::releases($item['release_dates'] ?? null, $now, $until),
        );
    }

    /**
     * Steam App ID z odkazů na obchody (`external_games`). Starší záznamy mají
     * zdroj ve vyřazeném poli `category`.
     */
    private static function steamAppId(mixed $externalGames): ?int
    {
        $steamSource = (int) config('games.upcoming.steam_source');
        foreach (is_array($externalGames) ? $externalGames : [] as $external) {
            if (! is_array($external)) {
                continue;
            }

            $source = $external['external_game_source'] ?? $external['category'] ?? null;
            $uid = $external['uid'] ?? null;
            if ($source === $steamSource && is_string($uid) && ctype_digit($uid)) {
                return (int) $uid;
            }
        }

        return null;
    }

    /**
     * ID obrázku (`image_id`) rozbaleného obrázku.
     */
    private static function imageId(mixed $image): ?string
    {
        $id = is_array($image) ? ($image['image_id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * ID prvního obrázku ze seznamu (artworks, screenshots).
     */
    private static function firstImageId(mixed $images): ?string
    {
        return is_array($images) && $images !== [] ? self::imageId(reset($images)) : null;
    }

    /**
     * Názvy žánrů.
     *
     * @return list<string>
     */
    private static function genres(mixed $genres): array
    {
        $names = [];
        foreach (is_array($genres) ? $genres : [] as $genre) {
            $name = is_array($genre) ? ($genre['name'] ?? null) : null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Hraje se ve víc lidech spolu? Režim kooperace nebo rozdělené obrazovky,
     * nebo offline co-op v `multiplayer_modes` — IGDB ho vede jen u pár her.
     *
     * @param  array<mixed>  $item
     */
    private static function isCoop(array $item): bool
    {
        $modes = is_array($item['game_modes'] ?? null) ? $item['game_modes'] : [];
        /** @var list<int> $coopModes */
        $coopModes = config('games.upcoming.coop_modes');
        if (array_intersect($modes, $coopModes) !== []) {
            return true;
        }

        foreach (is_array($item['multiplayer_modes'] ?? null) ? $item['multiplayer_modes'] : [] as $mode) {
            if (is_array($mode) && (($mode['offlinecoop'] ?? false) === true || ($mode['splitscreen'] ?? false) === true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nadcházející vydání po hlídaných platformách.
     *
     * @return array<string, array{date: CarbonImmutable, precision: ReleasePrecision, earlyAccess: bool}>
     */
    private static function releases(mixed $releaseDates, CarbonImmutable $now, CarbonImmutable $until): array
    {
        /** @var array<string, int> $platforms */
        $platforms = config('games.upcoming.platforms');
        $releases = [];
        foreach ($platforms as $platform => $igdbPlatform) {
            $release = self::upcomingOn($igdbPlatform, is_array($releaseDates) ? $releaseDates : [], $now, $until);
            if ($release !== null && Platform::tryFrom($platform) !== null) {
                $releases[$platform] = $release;
            }
        }

        return $releases;
    }

    /**
     * Nejbližší budoucí vydání (nebo start předběžného přístupu) na platformě;
     * null, když tam hra už vyšla nebo v okně vydání nemá.
     *
     * @param  array<mixed>  $releaseDates
     * @return array{date: CarbonImmutable, precision: ReleasePrecision, earlyAccess: bool}|null
     */
    private static function upcomingOn(int $igdbPlatform, array $releaseDates, CarbonImmutable $now, CarbonImmutable $until): ?array
    {
        /** @var list<int> $prerelease */
        $prerelease = config('games.upcoming.prerelease_statuses');
        $earlyAccess = (int) config('games.upcoming.early_access_status');
        $next = null;

        foreach ($releaseDates as $release) {
            if (! is_array($release) || ($release['platform'] ?? null) !== $igdbPlatform || ! is_int($release['date'] ?? null)) {
                continue;
            }

            $status = $release['status'] ?? null;
            $date = CarbonImmutable::createFromTimestampUTC($release['date']);
            $isFullRelease = ! in_array($status, $prerelease, true);

            if ($date->lessThanOrEqualTo($now)) {
                if ($isFullRelease) {
                    return null;
                }

                continue;
            }

            $precision = is_int($release['date_format'] ?? null) ? ReleasePrecision::fromIgdb($release['date_format']) : null;
            $isEarlyAccess = $status === $earlyAccess;
            if (($isFullRelease || $isEarlyAccess) && $precision !== null && $date->lessThanOrEqualTo($until) && ($next === null || $date->lessThan($next['date']))) {
                $next = ['date' => $date, 'precision' => $precision, 'earlyAccess' => $isEarlyAccess];
            }
        }

        return $next;
    }
}
