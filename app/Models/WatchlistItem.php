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

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seznam je osobní, katalog společný (R11). Platforma říká, kde uživatel hru
 * hlídá — null = všude (R24). Cílová cena přibude v etapě 6.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property Platform|null $platform
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
        ];
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
