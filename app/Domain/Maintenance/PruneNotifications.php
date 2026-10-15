<?php

/**
 * Úklid starých záznamů centra upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Maintenance;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Centrum upozornění drží záznamy games.notifications.retention_days dní (R53),
 * přečtené i nepřečtené. Scheduler hosting nemá (R2) — volá ho cron URL.
 */
final class PruneNotifications
{
    /**
     * Smaže starší záznamy; vrátí jejich počet.
     */
    public function handle(): int
    {
        $before = CarbonImmutable::now()->subDays((int) config('games.notifications.retention_days'));

        return (int) DatabaseNotification::query()->where('created_at', '<', $before)->delete();
    }
}
