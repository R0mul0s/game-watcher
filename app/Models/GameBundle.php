<?php

/**
 * Hra v aktivním bundlu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bundle z ITAD (R51) s cenou nejlevnější úrovně, která hru odemyká. Tabulku
 * celou přepisuje cron (RefreshBundles) — jen hry katalogu, jen aktivní bundly.
 *
 * @property int $id
 * @property int $game_id
 * @property int $bundle_id
 * @property string $title
 * @property string $shop
 * @property string $url
 * @property string|null $amount
 * @property string|null $currency
 * @property CarbonImmutable|null $expires_at
 */
class GameBundle extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'bundle_id',
        'title',
        'shop',
        'url',
        'amount',
        'currency',
        'expires_at',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_id' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * Cena úrovně s hrou; null u „Build your own“ bez ceny.
     */
    public function price(): ?Money
    {
        return $this->amount === null || $this->currency === null ? null : Money::fromDecimal($this->amount, $this->currency);
    }

    /**
     * Hra v bundlu.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
