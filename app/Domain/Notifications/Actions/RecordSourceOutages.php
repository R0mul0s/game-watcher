<?php

/**
 * Záznam o výpadku zdrojů cen v centru upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Monitoring\SourceHealth;
use App\Domain\Monitoring\SourceMonitor;
use App\Domain\Notifications\NotificationCenter;
use App\Enums\MonitoredSource;
use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Krok cron URL (R53): porovná hlídané zdroje s výpadkem (SourceMonitor::down(),
 * stejné jako upozornění na přehledu) s tím, co centrum ohlásilo minule,
 * a ozve se jen při změně — při novém výpadku (se všemi zdroji, které teď
 * nefungují) a jednou, když zase fungují všechny. Částečné zotavení nic
 * nezapíše. Záznam dostanou všechny účty — aplikace je soukromá a výpadek
 * se týká cen každého. Na zařízení ani e-mailem nejde.
 */
final class RecordSourceOutages
{
    /** Zdroje s výpadkem, které centrum naposledy ohlásilo (pole hodnot MonitoredSource). */
    private const CACHE_KEY = 'notifications.sources_down';

    public function __construct(
        private readonly SourceMonitor $monitor,
        private readonly NotificationCenter $center,
    ) {}

    /**
     * Zapíše změnu stavu zdrojů; vrátí počet zapsaných záznamů (0 = beze změny).
     */
    public function handle(): int
    {
        // Běh cronu vynechá — tenhle krok běží v cronu, takže cron zrovna běží
        // (stav běhu se zapisuje až po krocích, poprvé by tu byl „zatím neproběhlo“)
        $down = array_values(array_map(
            fn (SourceHealth $health): string => $health->source->value,
            array_filter($this->monitor->down(), fn (SourceHealth $health): bool => $health->source !== MonitoredSource::Cron),
        ));
        $stored = Cache::get(self::CACHE_KEY, []);
        $previous = is_array($stored) ? $stored : [];

        $newlyDown = array_diff($down, $previous);
        $recovered = $down === [] && $previous !== [];
        Cache::forever(self::CACHE_KEY, $down);

        if ($newlyDown === [] && ! $recovered) {
            return 0;
        }

        $count = 0;
        foreach (User::query()->orderBy('id')->get() as $user) {
            $this->center->record($user, NotificationKind::Sources, ['down' => $down]);
            $count++;
        }

        return $count;
    }
}
