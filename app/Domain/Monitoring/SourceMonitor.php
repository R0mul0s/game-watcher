<?php

/**
 * Záznam a vyhodnocení stavu zdrojů cron URL.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Monitoring;

use App\Enums\MonitoredSource;
use App\Models\SourceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stav zdrojů (R29): cron po každém kroku zapíše, jestli se zdroj povedl;
 * stránka stavu, upozornění na přehledu a /health/zdroje ho čtou přes report().
 * Změna formátu obchodu se tak pozná hned, ne až podle chybějících cen.
 */
final class SourceMonitor
{
    /** Délka sloupce source_statuses.last_error. */
    private const ERROR_LENGTH = 255;

    private const ELLIPSIS = '…';

    /**
     * Zapíše úspěšný pokus — vynuluje chyby v řadě, poslední chybu nechá
     * (stránka ji ukazuje i po zotavení).
     *
     * @param  int|null  $processed  počet zpracovaných položek
     * @param  int|null  $expected  kolik jich měl zdroj zpracovat
     */
    public function succeeded(MonitoredSource $source, ?int $processed = null, ?int $expected = null): void
    {
        $now = CarbonImmutable::now();

        SourceStatus::query()->updateOrCreate(['source' => $source->value], [
            'last_run_at' => $now,
            'last_success_at' => $now,
            'failures_in_row' => 0,
            'processed' => $processed,
            'expected' => $expected,
        ]);
    }

    /**
     * Zapíše neúspěšný pokus se zprávou (bez tajemství — viz describe()).
     */
    public function failed(MonitoredSource $source, string $error): void
    {
        $now = CarbonImmutable::now();
        $status = SourceStatus::query()->firstOrNew(['source' => $source->value]);

        $status->fill([
            'last_run_at' => $now,
            'last_failure_at' => $now,
            'last_error' => Str::limit($error, self::ERROR_LENGTH - mb_strlen(self::ELLIPSIS), self::ELLIPSIS),
            'failures_in_row' => (int) $status->failures_in_row + 1,
        ])->save();
    }

    /**
     * Zpráva výjimky pro stav zdrojů — u výpadku zdroje jeho zpráva, jinak jen
     * obecná hláška (zpráva cizí výjimky může obsahovat cokoli, třeba adresu s klíčem).
     */
    public function describe(Throwable $e): string
    {
        return $e instanceof SourceUnavailable ? $e->getMessage() : (string) __('app.monitoring.unexpected_error');
    }

    /**
     * Vyhodnocený stav všech zdrojů v pořadí kroků cronu.
     *
     * @return list<SourceHealth>
     */
    public function report(): array
    {
        $rows = SourceStatus::query()->get()->keyBy(fn (SourceStatus $status): string => $status->source->value);
        $now = CarbonImmutable::now();

        return array_map(fn (MonitoredSource $source): SourceHealth => SourceHealth::evaluate(
            $source,
            $rows->get($source->value),
            $now,
            $source === MonitoredSource::Cron
                ? (int) config('games.monitoring.cron_max_age_minutes')
                : (int) config('games.monitoring.stale_after_minutes'),
        ), MonitoredSource::cases());
    }

    /**
     * Hlídané zdroje, které mají výpadek (Steam se nehlídá, R25).
     *
     * @return list<SourceHealth>
     */
    public function down(): array
    {
        return array_values(array_filter(
            $this->report(),
            fn (SourceHealth $health): bool => $health->source->isCritical() && $health->state->isDown(),
        ));
    }
}
