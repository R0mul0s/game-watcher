<?php

/**
 * Odkaz na hru v PlayStation Store, který vložil uživatel.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation;

use Illuminate\Support\Str;

/**
 * Přijme jen adresu `store.playstation.com/<jazyk>/product/<ID>`,
 * `…/concept/<číslo>` nebo stránku hry `www.playstation.com/<jazyk>/games/<slug>/`
 * (R22) — server pak volá jen tyhle dva weby, nikdy adresu, kterou mu někdo
 * podstrčí (SSRF, docs/CODING_GUIDELINES.md, sekce 3). Jazyk z odkazu se
 * zahodí, stahuje se vždy česká verze (ceny v CZK).
 */
final readonly class PlayStationUrl
{
    public const KIND_PRODUCT = 'product';

    public const KIND_CONCEPT = 'concept';

    /** Stránka hry na prezentačním webu www.playstation.com/<jazyk>/games/<slug>/ (R22). */
    public const KIND_GAME_PAGE = 'game';

    /** ID produktu: EP5291-PPSA34547_00-0210080241603648 (region, titul, varianta). */
    public const PRODUCT_ID_PATTERN = '[A-Z]{2}\d{4}-[A-Z]{4}\d{5}_\d{2}-[A-Z0-9]{16}';

    /** Jazyk obchodu v cestě: cs-cz, en-us, zh-hant-tw. */
    private const LOCALE_PATTERN = '/[a-z]{2}(?:-[a-z]+)?-[a-z]{2}';

    private const PRODUCT_PATH_PATTERN = '~^'.self::LOCALE_PATTERN.'/product/('.self::PRODUCT_ID_PATTERN.')/?$~';

    private const CONCEPT_PATH_PATTERN = '~^'.self::LOCALE_PATTERN.'/concept/(\d{1,12})/?$~';

    private const GAME_PAGE_PATH_PATTERN = '~^'.self::LOCALE_PATTERN.'/games/([a-z0-9][a-z0-9-]{0,99})/?$~';

    /** Znaky, které adresa stránky hry vynechává (ochranné známky, apostrofy). */
    private const SLUG_DROPPED_CHARACTERS = ['™', '®', '©', '’', "'"];

    private function __construct(
        public string $kind,
        public string $id,
    ) {}

    /**
     * Rozpozná odkaz; jiný hostitel nebo tvar = null.
     */
    public static function parse(string $input): ?self
    {
        $input = trim($input);
        if (! preg_match('~^https?://~i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($host === config('games.sources.playstation.site_host')) {
            return preg_match(self::GAME_PAGE_PATH_PATTERN, $path, $match) === 1
                ? new self(self::KIND_GAME_PAGE, $match[1])
                : null;
        }

        if ($host !== config('games.sources.playstation.host')) {
            return null;
        }

        if (preg_match(self::PRODUCT_PATH_PATTERN, $path, $match) === 1) {
            return new self(self::KIND_PRODUCT, $match[1]);
        }

        return preg_match(self::CONCEPT_PATH_PATTERN, $path, $match) === 1
            ? new self(self::KIND_CONCEPT, $match[1])
            : null;
    }

    /**
     * Pravděpodobná adresa (slug) stránky hry na www.playstation.com podle
     * názvu (R24): „Marvel’s Spider-Man 2“ → marvels-spider-man-2, „Ghost of
     * Yōtei“ → ghost-of-yotei. Null, když z názvu nic nezbude nebo by slug
     * neprošel tvarem stránky hry.
     */
    public static function gamePageSlug(string $title): ?string
    {
        $slug = Str::slug(str_replace(self::SLUG_DROPPED_CHARACTERS, '', $title));

        return preg_match(self::GAME_PAGE_PATH_PATTERN, '/cs-cz/games/'.$slug.'/') === 1 ? $slug : null;
    }
}
