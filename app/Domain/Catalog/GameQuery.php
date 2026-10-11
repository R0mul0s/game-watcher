<?php

/**
 * Co uživatel zadal do hledání hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * Hru jde přidat podle názvu, Steam App ID nebo odkazu na Steam (etapa 2).
 * Číslo a odkaz se hledají přes `games/lookup` — přesná shoda, žádné
 * podobné názvy; text přes `games/search`.
 */
final readonly class GameQuery
{
    /** Odkaz na hru v obchodě Steam nebo v komunitě Steamu. */
    private const STEAM_URL_PATTERN = '~(?:store\.steampowered\.com|steamcommunity\.com)/app/(\d+)~i';

    /** Samotné Steam App ID. */
    private const APP_ID_PATTERN = '/^\d{1,10}$/';

    private function __construct(
        public ?string $title,
        public ?int $steamAppId,
    ) {}

    /**
     * Rozpozná druh dotazu; prázdný vstup vrátí jako null.
     */
    public static function parse(string $input): ?self
    {
        $input = trim(preg_replace('/\s+/u', ' ', $input) ?? '');
        if ($input === '') {
            return null;
        }

        if (preg_match(self::APP_ID_PATTERN, $input) === 1) {
            return new self(null, (int) $input);
        }

        if (preg_match(self::STEAM_URL_PATTERN, $input, $match) === 1) {
            return new self(null, (int) $match[1]);
        }

        return new self($input, null);
    }

    /**
     * Klíč pro cache výsledků — stejný dotaz s jinou velikostí písmen je stejný.
     */
    public function cacheKey(): string
    {
        return $this->steamAppId !== null
            ? 'steam:'.$this->steamAppId
            : 'title:'.sha1(mb_strtolower((string) $this->title));
    }
}
