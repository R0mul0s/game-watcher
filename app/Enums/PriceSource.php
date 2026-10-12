<?php

/**
 * Odkud cena pochází.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Zdroje cen (R9). Přímé zdroje (PlayStation, Kinguin, Eneba) přibudou v etapě 5.
 */
enum PriceSource: string
{
    /** IsThereAnyDeal — oficiální obchody po jednom */
    case Itad = 'itad';

    /** gg.deals — jen nejnižší cena obchodů s klíči */
    case GgDeals = 'ggdeals';
}
