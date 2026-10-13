<?php

/**
 * Úroveň předplatného PlayStation Plus.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * PS Store ji u tlačítek předplatného uvádí jako `tierLabel` (R35): TIER_10 =
 * Essential (měsíční hry, slevy), TIER_20 = Extra (katalog her), TIER_30 =
 * Premium (katalog klasik, zkušební verze).
 */
enum PlusTier: string
{
    case Essential = 'essential';

    case Extra = 'extra';

    case Premium = 'premium';

    /** `tierLabel` ze stránky produktu podle úrovně. */
    private const TIER_LABELS = [
        'TIER_10' => self::Essential,
        'TIER_20' => self::Extra,
        'TIER_30' => self::Premium,
    ];

    /**
     * Úroveň z `tierLabel`; neznámý štítek (jiné předplatné, EA Play) = null.
     */
    public static function fromTierLabel(mixed $label): ?self
    {
        return is_string($label) ? (self::TIER_LABELS[$label] ?? null) : null;
    }
}
