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
 * minimu (R26); měsíční hra PS Plus Essential pro účet s PS Plus (R35).
 */
enum AlertReason: string
{
    case Target = 'target';

    case HistoricalLow = 'low';

    case PlusMonthly = 'plus_monthly';
}
