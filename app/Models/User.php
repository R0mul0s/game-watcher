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

use App\Enums\EmailFrequency;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Účet zakládá správce (games:create-user), registrace není (R4). Upozornění
 * se zapisují do centra upozornění (databázové notifikace, R53), chodí na
 * zařízení s odběrem Web Push (R13) a e-mailem podle email_frequency.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $display_currency
 * @property EmailFrequency $email_frequency
 * @property CarbonImmutable|null $alert_email_sent_at
 * @property bool $has_ps_plus
 * @property bool $has_game_pass
 * @property bool $notify_weekly
 * @property CarbonImmutable|null $weekly_digest_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'display_currency',
        'email_frequency',
        'alert_email_sent_at',
        'has_ps_plus',
        'has_game_pass',
        'notify_weekly',
        'weekly_digest_at',
    ];

    /**
     * Výchozí hodnoty jako v databázi — platí i pro nově vytvořený model
     * před znovunačtením.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'email_frequency' => 'daily',
        'has_ps_plus' => false,
        'has_game_pass' => false,
        'notify_weekly' => true,
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
            'email_frequency' => EmailFrequency::class,
            'alert_email_sent_at' => 'immutable_datetime',
            'has_ps_plus' => 'boolean',
            'has_game_pass' => 'boolean',
            'notify_weekly' => 'boolean',
            'weekly_digest_at' => 'immutable_datetime',
        ];
    }

    /**
     * Zařízení s odběrem upozornění (Web Push, R13).
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
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
     * Měna zobrazení cen (R14); neznámá hodnota = výchozí měna z konfigurace.
     */
    public function displayCurrency(): string
    {
        /** @var list<string> $options */
        $options = config('games.prices.display_currencies');

        return in_array($this->display_currency, $options, true)
            ? $this->display_currency
            : (string) config('games.prices.default_currency');
    }

    /**
     * Sleduje uživatel tuhle hru?
     */
    public function isWatching(Game $game): bool
    {
        return $this->watchlistItems()->where('game_id', $game->id)->exists();
    }

    /**
     * Položka seznamu pro hru — null, když ji uživatel nesleduje.
     */
    public function watchlistItemFor(Game $game): ?WatchlistItem
    {
        return $this->watchlistItems()->where('game_id', $game->id)->first();
    }

    /**
     * Položky seznamu podle ID hry — hlídaná platforma (R24) a cílová cena (R26).
     *
     * @return Collection<int, WatchlistItem>
     */
    public function watchlistItemsByGame(): Collection
    {
        return $this->watchlistItems()->get()->keyBy('game_id');
    }
}
