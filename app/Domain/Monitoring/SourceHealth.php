<?php

/**
 * Vyhodnocený stav jednoho zdroje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Monitoring;

use App\Enums\MonitoredSource;
use App\Enums\SourceState;
use App\Models\SourceStatus;
use Carbon\CarbonImmutable;

/**
 * Řádek stavu zdrojů (R29) — uložený stav a jeho vyhodnocení vůči mezi stáří.
 */
final readonly class SourceHealth
{
    public function __construct(
        public MonitoredSource $source,
        public SourceState $state,
        public ?CarbonImmutable $lastRunAt = null,
        public ?CarbonImmutable $lastSuccessAt = null,
        public ?CarbonImmutable $lastFailureAt = null,
        public ?string $lastError = null,
        public int $failuresInRow = 0,
        public ?int $processed = null,
        public ?int $expected = null,
    ) {}

    /**
     * Vyhodnotí uložený stav. U cronu rozhoduje stáří posledního běhu (hosting
     * ho přestal volat), u zdrojů stáří posledního úspěchu.
     *
     * @param  int  $maxAgeMinutes  mez stáří pro tenhle zdroj
     */
    public static function evaluate(MonitoredSource $source, ?SourceStatus $status, CarbonImmutable $now, int $maxAgeMinutes): self
    {
        $reference = $source === MonitoredSource::Cron ? $status?->last_run_at : $status?->last_success_at;

        $state = match (true) {
            $status?->last_run_at === null => SourceState::Never,
            $reference === null || $reference->lessThan($now->subMinutes($maxAgeMinutes)) => SourceState::Stale,
            $status->failures_in_row > 0 => SourceState::Failing,
            default => SourceState::Ok,
        };

        return new self(
            source: $source,
            state: $state,
            lastRunAt: $status?->last_run_at,
            lastSuccessAt: $status?->last_success_at,
            lastFailureAt: $status?->last_failure_at,
            lastError: $status?->last_error,
            failuresInRow: $status->failures_in_row ?? 0,
            processed: $status?->processed,
            expected: $status?->expected,
        );
    }

    /**
     * Čas, podle kterého se stav posuzuje — u cronu poslední běh, u zdroje poslední úspěch.
     */
    public function referenceAt(): ?CarbonImmutable
    {
        return $this->source === MonitoredSource::Cron ? $this->lastRunAt : $this->lastSuccessAt;
    }

    /**
     * Data pro stránku stavu — časy jako ISO 8601 (CODING_GUIDELINES, sekce 4).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'state' => $this->state->value,
            'critical' => $this->source->isCritical(),
            'lastRunAt' => $this->lastRunAt?->toIso8601String(),
            'lastSuccessAt' => $this->lastSuccessAt?->toIso8601String(),
            'lastFailureAt' => $this->lastFailureAt?->toIso8601String(),
            'lastError' => $this->lastError,
            'failuresInRow' => $this->failuresInRow,
            'processed' => $this->processed,
            'expected' => $this->expected,
        ];
    }
}
