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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seznam je osobní, katalog společný (R11). Cílová cena přibude v etapě 6.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 */
class WatchlistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'game_id',
    ];

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
