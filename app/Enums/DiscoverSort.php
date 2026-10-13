<?php

/**
 * Řazení stránky Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volby řazení slev z ITAD (R43); hodnota pro API je řazení webu ITAD —
 * ověřené požadavkem (neznámou hodnotu ITAD odmítne HTTP 400).
 */
enum DiscoverSort: string
{
    /** Řazení bez volby v adrese. */
    public const DEFAULT = self::Popular;

    /** Nejoblíbenější hry (pořadí podle ITAD) */
    case Popular = 'popular';

    /** Co se teď hodně hledá a kupuje */
    case Trending = 'trending';

    /** Od největší slevy */
    case Cut = 'cut';

    /** Od nejlevnější */
    case Price = 'price';

    /** Nejnovější slevy */
    case Newest = 'newest';

    /**
     * Hodnota parametru `sort` pro ITAD `deals/v2`.
     */
    public function itadSort(): string
    {
        return match ($this) {
            self::Popular => 'rank',
            self::Trending => '-trending',
            self::Cut => '-cut',
            self::Price => 'price',
            self::Newest => '-time',
        };
    }
}
