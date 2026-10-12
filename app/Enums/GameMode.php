<?php

/**
 * Režim hry — kolik hráčů a jak spolu hrají.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Režimy ze Steamu (kategorie obchodu) a z PS Store (počet hráčů, R25).
 * „Lokálně“ = u jedné obrazovky (sdílená nebo rozdělená, couch co-op).
 */
enum GameMode: string
{
    case Single = 'single';

    case Multi = 'multi';

    case Coop = 'coop';

    case OnlineCoop = 'online_coop';

    case LocalCoop = 'local_coop';

    case LocalMulti = 'local_multi';

    /**
     * Kategorie Steamu (ID z appdetails) a režim, který znamená.
     *
     * @return array<int, self>
     */
    public static function steamCategories(): array
    {
        return [
            2 => self::Single,
            1 => self::Multi,
            36 => self::Multi,
            49 => self::Multi,
            9 => self::Coop,
            48 => self::Coop,
            38 => self::OnlineCoop,
            39 => self::LocalCoop,
            24 => self::LocalMulti,
            37 => self::LocalMulti,
        ];
    }
}
