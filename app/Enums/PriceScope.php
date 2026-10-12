<?php

/**
 * Skupina obchodů pro historické minimum.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Minimum oficiálních obchodů dává ITAD, minimum obchodů s klíči gg.deals (R9).
 */
enum PriceScope: string
{
    case Official = 'official';

    case Keyshops = 'keyshops';
}
