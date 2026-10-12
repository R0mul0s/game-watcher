<?php

/**
 * Historicky nejnižší cena hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Money;
use App\Enums\PriceScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimum oficiálních obchodů (ITAD) a obchodů s klíči (gg.deals) — jak ho
 * zdroj uvádí, aplikace ho nepočítá. Složený klíč (game_id, scope): zapisuje se
 * jen hromadně (upsert), nikdy přes save().
 *
 * @property int $game_id
 * @property PriceScope $scope
 * @property string $amount
 * @property string $currency
 * @property CarbonImmutable $fetched_at
 */
class PriceLow extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'scope',
        'amount',
        'currency',
        'fetched_at',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => PriceScope::class,
            'amount' => 'decimal:2',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    /**
     * Minimum jako částka.
     */
    public function price(): Money
    {
        return Money::fromDecimal($this->amount, $this->currency);
    }
}
