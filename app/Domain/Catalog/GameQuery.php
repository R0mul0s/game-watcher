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
 * Hru jde přidat podle názvu, Steam App ID nebo odkazu na Steam (etapa 2)
 * a podle odkazu na IsThereAnyDeal (R30). Číslo a odkaz na Steam se hledají
 * přes `games/lookup` — přesná shoda, žádné podobné názvy; text přes
 * `games/search`. ITAD lookup podle slugu nemá — odkaz na ITAD se hledá jako
 * název ze slugu a z výsledků se vezme hra s přesně tímto slugem.
 */
final readonly class GameQuery
{
    /** Odkaz na hru v obchodě Steam nebo v komunitě Steamu. */
    private const STEAM_URL_PATTERN = '~(?:store\.steampowered\.com|steamcommunity\.com)/app/(\d+)~i';

    /** Odkaz na hru na IsThereAnyDeal (isthereanydeal.com/game/<slug>/). */
    private const ITAD_URL_PATTERN = '~isthereanydeal\.com/game/([a-z0-9]+(?:-[a-z0-9]+)*)~i';

    /** Oddělovač slov ve slugu ITAD. */
    private const SLUG_SEPARATOR = '-';

    /** Samotné Steam App ID. */
    private const APP_ID_PATTERN = '/^\d{1,10}$/';

    private function __construct(
        public ?string $title,
        public ?int $steamAppId,
        public ?string $itadSlug = null,
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

        if (preg_match(self::ITAD_URL_PATTERN, $input, $match) === 1) {
            $slug = strtolower($match[1]);

            return new self(str_replace(self::SLUG_SEPARATOR, ' ', $slug), null, $slug);
        }

        return new self($input, null);
    }

    /**
     * Hledání podle názvu bez rozpoznávání Steam App ID — název z PS Store
     * může být i samé číslo (R24).
     */
    public static function title(string $title): self
    {
        return new self(trim(preg_replace('/\s+/u', ' ', $title) ?? $title), null);
    }

    /**
     * Klíč pro cache výsledků — stejný dotaz s jinou velikostí písmen je stejný.
     */
    public function cacheKey(): string
    {
        return match (true) {
            $this->steamAppId !== null => 'steam:'.$this->steamAppId,
            $this->itadSlug !== null => 'itad:'.$this->itadSlug,
            default => 'title:'.sha1(mb_strtolower((string) $this->title)),
        };
    }
}
