<?php

/**
 * Přesnost data vydání nadcházející hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * IGDB zná datum vydání na den, měsíc, čtvrtletí nebo jen rok (R46) — uložené
 * datum je pak den uvnitř období (IGDB: u měsíce první den, u čtvrtletí a roku
 * poslední — Q1 2027 = 31. 3. 2027), stránka ho ukazuje v přesnosti zdroje.
 */
enum ReleasePrecision: string
{
    case Day = 'day';

    case Month = 'month';

    case Quarter = 'quarter';

    case Year = 'year';

    /** Formáty data IGDB (`date_format`): 0 den, 1 měsíc, 2 rok, 3–6 čtvrtletí; 7 = TBD (bez data). */
    private const IGDB_FORMATS = [0 => self::Day, 1 => self::Month, 2 => self::Year, 3 => self::Quarter, 4 => self::Quarter, 5 => self::Quarter, 6 => self::Quarter];

    /**
     * Přesnost z formátu data IGDB; null pro „TBD“ a neznámý formát.
     */
    public static function fromIgdb(int $dateFormat): ?self
    {
        return self::IGDB_FORMATS[$dateFormat] ?? null;
    }
}
