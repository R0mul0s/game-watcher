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
 * Volby řazení ve filtrech přehledu (R21); výchozí jsou naposledy přidané.
 */
enum WatchlistSort: string
{
    case Added = 'added';

    case Title = 'title';

    /** Od nejlevnější podle nejlevnější nabídky ve filtru */
    case Price = 'price';

    /** Od největší slevy */
    case Cut = 'cut';

    /** Od nejnovějšího vydání */
    case Release = 'release';
}
