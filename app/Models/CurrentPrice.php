<?php

/**
 * Aktuální cena hry v jednom obchodě.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Money;
use App\Enums\Platform;
use App\Enums\PlusTier;
use App\Enums\PriceSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plní ji StorePrices (R9) — při každém stažení se nabídky zdroje pro hru
 * nahradí, obchod, který hru přestal nabízet, zmizí.
 *
 * @property int $id
 * @property int $game_id
 * @property PriceSource $source
 * @property string $shop_key
 * @property string $shop_name
 * @property Platform $platform
 * @property string|null $edition
 * @property string $amount
 * @property string|null $regular_amount
 * @property string|null $member_amount
 * @property bool $member_included
 * @property PlusTier|null $member_tier
 * @property bool $member_free
 * @property string $currency
 * @property int $cut
 * @property string|null $drm
 * @property string|null $url
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $fetched_at
 */
class CurrentPrice extends Model
{
    /** Klíč řádku s nejnižší cenou obchodů s klíči (gg.deals). */
    public const KEYSHOPS_KEY = 'keyshops';

    /** Předpona klíče obchodu z ITAD (itad-61 = Steam). */
    public const ITAD_KEY_PREFIX = 'itad-';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'source',
        'shop_key',
        'shop_name',
        'platform',
        'edition',
        'amount',
        'regular_amount',
        'member_amount',
        'member_included',
        'member_tier',
        'member_free',
        'currency',
        'cut',
        'drm',
        'url',
        'expires_at',
        'fetched_at',
    ];

    /**
     * Přetypování atributů — částky zůstávají řetězcem (decimal), převod přes Money.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PriceSource::class,
            'amount' => 'decimal:2',
            'regular_amount' => 'decimal:2',
            'platform' => Platform::class,
            'member_amount' => 'decimal:2',
            'member_included' => 'boolean',
            'member_tier' => PlusTier::class,
            'member_free' => 'boolean',
            'cut' => 'integer',
            'expires_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    /**
     * Hra, ke které cena patří.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Aktuální cena jako částka.
     */
    public function price(): Money
    {
        return Money::fromDecimal($this->amount, $this->currency);
    }

    /**
     * Cena pro předplatitele (PS Plus), když je nižší než běžná (R20).
     */
    public function memberPrice(): ?Money
    {
        return $this->member_amount === null ? null : Money::fromDecimal($this->member_amount, $this->currency);
    }

    /**
     * Běžná cena bez slevy, když ji zdroj uvádí.
     */
    public function regularPrice(): ?Money
    {
        return $this->regular_amount === null ? null : Money::fromDecimal($this->regular_amount, $this->currency);
    }
}
