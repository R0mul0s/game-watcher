<?php

/**
 * Jak často chodí e-mail s upozorněními.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volba účtu (users.email_frequency, R53). E-mail skládá záznamy centra
 * upozornění, které ještě nepřišly e-mailem — souhrn ráno, nebo hned po běhu
 * cronu, který je zapsal.
 */
enum EmailFrequency: string
{
    /** Jeden souhrn denně — první běh cronu po games.alerts.digest_hour. */
    case Daily = 'daily';

    /** Po každém běhu cronu, který něco zapsal (dřívější chování, R26). */
    case Instant = 'instant';

    case Off = 'off';
}
