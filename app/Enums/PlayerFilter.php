<?php

/**
 * Filtr přehledu podle počtu hráčů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Pro jednoho, pro dva (co-op nebo hra pro víc hráčů) a couch co-op — hra
 * u jedné obrazovky (R25).
 */
enum PlayerFilter: string
{
    case Solo = 'solo';

    case Duo = 'duo';

    case Couch = 'couch';

    /**
     * Odpovídá hra s těmito režimy filtru?
     *
     * @param  list<GameMode>  $modes
     */
    public function matches(array $modes): bool
    {
        $accepted = match ($this) {
            self::Solo => [GameMode::Single],
            self::Duo => [GameMode::Multi, GameMode::Coop, GameMode::OnlineCoop, GameMode::LocalCoop, GameMode::LocalMulti],
            self::Couch => [GameMode::LocalCoop, GameMode::LocalMulti],
        };

        return array_intersect(
            array_map(fn (GameMode $mode): string => $mode->value, $modes),
            array_map(fn (GameMode $mode): string => $mode->value, $accepted),
        ) !== [];
    }
}
