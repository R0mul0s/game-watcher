<?php

/**
 * Hra v katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Údaje přebírá z IsThereAnyDeal `ImportGame` — identitou je `itad_id` (R10).
 * Katalog je společný pro všechny účty (R11).
 *
 * @property int $id
 * @property string $itad_id
 * @property string $slug
 * @property string $title
 * @property string $type
 * @property bool $is_mature
 * @property int|null $steam_app_id
 * @property CarbonImmutable|null $release_date
 * @property list<string>|null $developers
 * @property list<string>|null $publishers
 * @property list<string>|null $tags
 * @property list<array{source: string, score: int, count: int|null, url: string|null}>|null $reviews
 * @property string|null $boxart_url
 * @property string|null $banner_url
 * @property string|null $itad_url
 * @property CarbonImmutable $info_refreshed_at
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'itad_id',
        'slug',
        'title',
        'type',
        'is_mature',
        'steam_app_id',
        'release_date',
        'developers',
        'publishers',
        'tags',
        'reviews',
        'boxart_url',
        'banner_url',
        'itad_url',
        'info_refreshed_at',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mature' => 'boolean',
            'steam_app_id' => 'integer',
            'release_date' => 'immutable_date',
            'developers' => 'array',
            'publishers' => 'array',
            'tags' => 'array',
            'reviews' => 'array',
            'info_refreshed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Adresa detailu je podle slugu z ITAD (/hry/control-resonant), ne podle ID.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Záznamy „sleduji“ všech účtů, které hru sledují.
     *
     * @return HasMany<WatchlistItem, $this>
     */
    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    /**
     * Aktuální ceny v obchodech (R9).
     *
     * @return HasMany<CurrentPrice, $this>
     */
    public function currentPrices(): HasMany
    {
        return $this->hasMany(CurrentPrice::class);
    }

    /**
     * Historická minima — oficiální obchody a obchody s klíči.
     *
     * @return HasMany<PriceLow, $this>
     */
    public function priceLows(): HasMany
    {
        return $this->hasMany(PriceLow::class);
    }

    /**
     * Adresa hry v obchodě Steam, když ji Steam prodává.
     */
    public function steamUrl(): ?string
    {
        return $this->steam_app_id === null
            ? null
            : sprintf((string) config('games.catalog.steam_store_url'), $this->steam_app_id);
    }
}
