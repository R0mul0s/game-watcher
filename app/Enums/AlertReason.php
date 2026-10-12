<?php

/**
 * Proč přišlo upozornění na cenu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Cena pod cílovou cenou, nebo — u hry bez cílové ceny — na historickém
 * minimu (R26).
 */
enum AlertReason: string
{
    case Target = 'target';

    case HistoricalLow = 'low';
}
