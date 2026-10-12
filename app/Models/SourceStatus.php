<?php

/**
 * Stav jednoho zdroje cron URL.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitoredSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Zapisuje jen SourceMonitor (R29), jeden řádek na zdroj.
 *
 * @property int $id
 * @property MonitoredSource $source
 * @property CarbonImmutable|null $last_run_at
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property string|null $last_error
 * @property int $failures_in_row
 * @property int|null $processed
 * @property int|null $expected
 */
class SourceStatus extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'last_run_at',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'failures_in_row',
        'processed',
        'expected',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => MonitoredSource::class,
            'last_run_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
            'failures_in_row' => 'integer',
            'processed' => 'integer',
            'expected' => 'integer',
        ];
    }
}
