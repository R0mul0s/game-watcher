<?php

/**
 * Souhrn jednoho rozeslání upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

/**
 * Počty pro řádek souhrnu cron URL.
 */
final class AlertRunResult
{
    /** Upozornění na hry (napříč účty). */
    public int $alerts = 0;

    /** Odeslané e-maily (jeden na účet). */
    public int $emails = 0;

    /** Doručené zprávy na zařízení. */
    public int $pushes = 0;

    /** Upozornění, která nedoručil žádný kanál — zkusí je další běh. */
    public int $undelivered = 0;
}
