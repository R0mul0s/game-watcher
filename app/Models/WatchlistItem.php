<?php

/**
 * Hra na osobním seznamu sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Money;
use App\Enums\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seznam je osobní, katalog společný (R11). Platforma říká, kde uživatel hru
 * hlídá — null = všude (R24). Cílová cena v měně, ve které ji uživatel zadal;
 * bez ní se upozorňuje na historické minimum. Poslední upozorněná cena brání
 * tomu, aby stejná cena přišla podruhé (R26). Upozornění na vydání a první
 * slevu (R41) mají vlastní stav — hra sledovaná až po vydání ho má hned vyplněný.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property Platform|null $platform
 * @property string|null $target_amount
 * @property string|null $target_currency
 * @property string|null $notified_amount
 * @property string|null $notified_currency
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $release_notified_at
 * @property CarbonImmutable|null $first_sale_notified_at
 * @property CarbonImmutable|null $game_pass_notified_at
 * @property list<int>|null $notified_bundles
 * @property CarbonImmutable|null $created_at
 */
class WatchlistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'game_id',
        'platform',
        'target_amount',
        'target_currency',
        'notified_amount',
        'notified_currency',
        'notified_at',
        'release_notified_at',
        'first_sale_notified_at',
        'game_pass_notified_at',
        'notified_bundles',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'notified_at' => 'immutable_datetime',
            'release_notified_at' => 'immutable_datetime',
            'first_sale_notified_at' => 'immutable_datetime',
            // Upozornění na Game Pass a bundly (R50, R51)
            'game_pass_notified_at' => 'immutable_datetime',
            'notified_bundles' => 'array',
        ];
    }

    /**
     * Cílová cena; null = upozornit na historické minimum.
     */
    public function target(): ?Money
    {
        return $this->target_amount === null || $this->target_currency === null
            ? null
            : Money::fromDecimal($this->target_amount, $this->target_currency);
    }

    /**
     * Cena z posledního upozornění; null = podmínka upozornění teď neplatí.
     */
    public function notifiedPrice(): ?Money
    {
        return $this->notified_amount === null || $this->notified_currency === null
            ? null
            : Money::fromDecimal($this->notified_amount, $this->notified_currency);
    }

    /**
     * Účet, kterému položka patří.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sledovaná hra.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
