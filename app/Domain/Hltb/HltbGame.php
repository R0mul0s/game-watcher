<?php

/**
 * Hra na HowLongToBeat s délkami hraní.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb;

/**
 * Položka hledání (`/api/search/site`) i stránky hry (`__NEXT_DATA__`) mají
 * stejná pole: `comp_main`, `comp_plus`, `comp_100` jsou průměrné délky
 * v sekundách, 0 = bez údaje (R44). Rok vydání hledání dává jako číslo,
 * stránka jako datum.
 */
final readonly class HltbGame
{
    private const SECONDS_PER_MINUTE = 60;

    /** Rok na začátku data vydání („2021-05-25“). */
    private const YEAR_LENGTH = 4;

    public function __construct(
        public int $id,
        public string $name,
        public ?int $mainMinutes,
        public ?int $extraMinutes,
        public ?int $completeMinutes,
        public ?int $releaseYear,
    ) {}

    /**
     * Hra z JSON objektu HLTB; bez ID nebo názvu null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        if (! is_int($data['game_id'] ?? null) || ! is_string($data['game_name'] ?? null)) {
            return null;
        }

        $release = $data['release_world'] ?? null;
        $year = match (true) {
            is_int($release) && $release > 0 => $release,
            is_string($release) && strlen($release) >= self::YEAR_LENGTH => (int) substr($release, 0, self::YEAR_LENGTH),
            default => null,
        };

        return new self(
            id: $data['game_id'],
            name: $data['game_name'],
            mainMinutes: self::minutes($data['comp_main'] ?? null),
            extraMinutes: self::minutes($data['comp_plus'] ?? null),
            completeMinutes: self::minutes($data['comp_100'] ?? null),
            releaseYear: $year,
        );
    }

    /**
     * Sekundy na celé minuty; 0 a chybějící hodnota = null.
     */
    private static function minutes(mixed $seconds): ?int
    {
        return is_int($seconds) && $seconds > 0 ? (int) round($seconds / self::SECONDS_PER_MINUTE) : null;
    }
}
