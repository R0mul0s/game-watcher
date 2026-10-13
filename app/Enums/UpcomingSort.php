<?php

/**
 * Řazení nadcházejících her na stránce Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volby řazení záložky Nadcházející (R46).
 */
enum UpcomingSort: string
{
    /** Řazení bez volby v adrese. */
    public const DEFAULT = self::Date;

    /** Od nejbližšího vydání (při filtru platformy podle vydání na ní) */
    case Date = 'date';

    /** Od nejočekávanější — popularita IGDB (`hypes`) */
    case Popular = 'popular';
}
