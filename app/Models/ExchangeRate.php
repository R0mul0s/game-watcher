<?php

/**
 * Kurz měny ČNB pro jeden den.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Plní ho ImportExchangeRates (R14). Složený klíč (day, currency): zapisuje
 * se jen hromadně (upsert), nikdy přes save().
 *
 * @property CarbonImmutable $day
 * @property string $currency
 * @property string $rate
 */
class ExchangeRate extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day',
        'currency',
        'rate',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'rate' => 'decimal:6',
        ];
    }
}
