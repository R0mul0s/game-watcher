<?php

/**
 * Filtr přehledu podle vydání hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

use App\Models\Game;

/**
 * Vydané a nevydané hry (R41) — hra bez data vydání se počítá jako nevydaná:
 * ITAD datum nemá právě u her, které teprve vyjdou.
 */
enum ReleaseFilter: string
{
    case Released = 'released';

    case Upcoming = 'upcoming';

    /**
     * Odpovídá hra filtru?
     */
    public function matches(Game $game): bool
    {
        return $game->isReleased() === ($this === self::Released);
    }
}
