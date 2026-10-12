<?php

/**
 * Řazení výsledků hledání hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volby řazení na stránce „Přidat hru“ (R30). Datum vydání ve výsledcích není —
 * `games/search` ho nevrací a `games/info` pro každou hru by vyčerpalo limit ITAD.
 */
enum SearchSort: string
{
    /** Pořadí z ITAD (přesná shoda názvu první, R22) — výchozí */
    case Relevance = 'relevance';

    /** Od nejlevnější nabídky v obchodech pro PC, hry bez ceny na konci */
    case Price = 'price';
}
