<?php

/**
 * Odkaz na stránku hry na Metacritic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic;

use Illuminate\Support\Str;

/**
 * Jediné místo, kde se ověřuje odkaz od uživatele (SSRF) — přijme jen
 * hostitele z games.sources.metacritic.hosts a cestu /game/<slug>/; server pak
 * zavolá adresu složenou z konfigurace, ne z odkazu (R33).
 */
final class MetacriticUrl
{
    /** Cesta ke hře: /game/<slug>/ s volitelnou podstránkou (critic-reviews…). */
    private const GAME_PATH_PATTERN = '~^/game/([a-z0-9][a-z0-9-]{0,119})(?:/[a-z0-9/-]*)?$~';

    /** Znaky, které Metacritic ve slugu vynechá (ne nahradí pomlčkou). */
    private const SLUG_DROPPED_CHARACTERS = ['™', '®', '©', '’', "'"];

    /** Tvar slugu pro kontrolu odvozeného slugu. */
    private const SLUG_PATTERN = '~^[a-z0-9][a-z0-9-]{0,119}$~';

    /**
     * Slug hry z odkazu; jiný hostitel nebo tvar = null.
     */
    public static function parse(string $input): ?string
    {
        $input = trim($input);
        if (! preg_match('~^https?://~i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts) || ! in_array(strtolower($parts['host'] ?? ''), (array) config('games.sources.metacritic.hosts'), true)) {
            return null;
        }

        return preg_match(self::GAME_PATH_PATTERN, strtolower($parts['path'] ?? ''), $match) === 1 ? $match[1] : null;
    }

    /**
     * Pravděpodobný slug stránky hry podle názvu („Ghost of Yōtei“ →
     * ghost-of-yotei). Metacritic ale někdy slug pojmenuje jinak (Alan Wake 2 je
     * alan-wake-ii) — pak zbývá odkaz od uživatele. Null, když z názvu nic nezbude.
     */
    public static function slugFromTitle(string $title): ?string
    {
        $slug = Str::slug(str_replace(self::SLUG_DROPPED_CHARACTERS, '', $title));

        return preg_match(self::SLUG_PATTERN, $slug) === 1 ? $slug : null;
    }

    /**
     * Adresa stránky hry.
     */
    public static function game(string $slug): string
    {
        return rtrim((string) config('games.sources.metacritic.url'), '/').'/game/'.$slug.'/';
    }

    /**
     * Adresa recenzí kritiků — odkaz u Metascore na detailu hry.
     */
    public static function criticReviews(string $slug): string
    {
        return self::game($slug).'critic-reviews/';
    }
}
