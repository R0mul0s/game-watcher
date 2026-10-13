<?php

/**
 * Odkaz na hru na HowLongToBeat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb;

/**
 * Jediné místo, kde se ověřuje odkaz od uživatele (SSRF) — přijme jen
 * hostitele z games.sources.hltb.hosts a cestu /game/<id>; server pak zavolá
 * adresu složenou z konfigurace, ne z odkazu (R44).
 */
final class HltbUrl
{
    /** Cesta ke hře: /game/<číslo> s volitelnou podstránkou. */
    private const GAME_PATH_PATTERN = '~^/game/([1-9][0-9]{0,9})(?:/.*)?$~';

    /**
     * ID hry z odkazu; jiný hostitel nebo tvar = null.
     */
    public static function parse(string $input): ?int
    {
        $input = trim($input);
        if (! preg_match('~^https?://~i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts) || ! in_array(strtolower($parts['host'] ?? ''), (array) config('games.sources.hltb.hosts'), true)) {
            return null;
        }

        return preg_match(self::GAME_PATH_PATTERN, $parts['path'] ?? '', $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * Adresa stránky hry.
     */
    public static function game(int $id): string
    {
        return rtrim((string) config('games.sources.hltb.url'), '/').'/game/'.$id;
    }
}
