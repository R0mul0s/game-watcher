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
 * Počty pro řádek souhrnu cron URL. E-mail chodí zvlášť ze záznamů centra
 * (SendAlertEmails, R53).
 */
final class AlertRunResult
{
    /** Upozornění na hry zapsaná do centra (napříč účty). */
    public int $alerts = 0;

    /** Doručené zprávy na zařízení. */
    public int $pushes = 0;
}
