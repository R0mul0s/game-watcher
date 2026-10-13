<?php

/**
 * Řazení přehledu sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volby řazení ve filtrech přehledu (R21); výchozí je od nejlevnější (R32).
 */
enum WatchlistSort: string
{
    /** Řazení bez volby v adrese. */
    public const DEFAULT = self::Price;

    case Added = 'added';

    case Title = 'title';

    /** Od nejlevnější podle ceny ze zvoleného zdroje (R30) */
    case Price = 'price';

    /** Od největší slevy */
    case Cut = 'cut';

    /** Od nejnovějšího vydání */
    case Release = 'release';

    /** Od nejlepšího hodnocení kritiků na Metacritic (R32) */
    case Score = 'score';

    /** Od nejnižší ceny za hodinu hlavního příběhu podle HowLongToBeat (R44) */
    case PerHour = 'per_hour';
}
