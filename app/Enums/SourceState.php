<?php

/**
 * Stav zdroje na stránce stavu a v monitoringu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Vyhodnocení řádku source_statuses (R29). Jeden neúspěšný běh je jen
 * „chyba“ — výpadek je, až poslední úspěch zestárne (zdroj selhal víckrát
 * po sobě) nebo zdroj ještě nikdy neproběhl.
 */
enum SourceState: string
{
    case Ok = 'ok';

    /** Poslední pokus selhal, poslední úspěch je ale ještě čerstvý */
    case Failing = 'failing';

    /** Poslední úspěch je starší než mez (u cronu poslední běh) */
    case Stale = 'stale';

    /** Zdroj ještě nikdy neproběhl */
    case Never = 'never';

    /**
     * Je to výpadek, který má hlásit monitoring?
     */
    public function isDown(): bool
    {
        return $this === self::Stale || $this === self::Never;
    }
}
