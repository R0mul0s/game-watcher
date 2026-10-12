<?php

/**
 * Platforma, pro kterou je cena.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Obchody z ITAD a gg.deals prodávají verzi pro PC, PlayStation Store
 * konzolovou (R20) — ve výpisu cen jsou spolu, filtr je rozliší.
 */
enum Platform: string
{
    case Pc = 'pc';

    case PlayStation = 'playstation';
}
