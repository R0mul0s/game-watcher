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
 * Zdroje cen (R9). Přímé zdroje Kinguin a Eneba přibudou později (etapa 5);
 * krabicové hry z Xzone a Heureky (R52).
 */
enum PriceSource: string
{
    /** IsThereAnyDeal — oficiální obchody po jednom */
    case Itad = 'itad';

    /** gg.deals — jen nejnižší cena obchodů s klíči */
    case GgDeals = 'ggdeals';

    /** PlayStation Store — stránky produktů propojených se hrou (R20) */
    case PlayStation = 'playstation';

    /** Xzone — krabicová hra na PS5 připojená odkazem (R52) */
    case Xzone = 'xzone';

    /** Heureka — nejnižší cena krabicové hry napříč obchody (R52) */
    case Heureka = 'heureka';

    /**
     * Obchod s krabicovými hrami (R52) — produkt připojený odkazem, cena ze schema.org.
     */
    public function isRetail(): bool
    {
        return in_array($this, [self::Xzone, self::Heureka], true);
    }
}
