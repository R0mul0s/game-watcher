<?php

/**
 * Uživatelský účet.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Účet zakládá správce (games:create-user), registrace není (R4).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Přetypování atributů — heslo se při uložení samo zahashuje.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Osobní seznam sledovaných her (R11).
     *
     * @return HasMany<WatchlistItem, $this>
     */
    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    /**
     * Sledované hry, naposledy přidané první.
     *
     * @return BelongsToMany<Game, $this>
     */
    public function watchedGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'watchlist_items')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc')
            ->orderByPivot('id', 'desc');
    }

    /**
     * Sleduje uživatel tuhle hru?
     */
    public function isWatching(Game $game): bool
    {
        return $this->watchlistItems()->where('game_id', $game->id)->exists();
    }
}
