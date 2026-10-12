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

use App\Enums\GameMode;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Údaje přebírá z IsThereAnyDeal `ImportGame` — identitou je `itad_id` (R10).
 * Katalog je společný pro všechny účty (R11). Žánry a režimy jsou ze Steamu
 * (`RefreshGameDetails`, R25).
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
 * @property list<string>|null $genres
 * @property list<string>|null $modes
 * @property list<array{source: string, score: int, count: int|null, url: string|null}>|null $reviews
 * @property string|null $boxart_url
 * @property string|null $banner_url
 * @property string|null $itad_url
 * @property CarbonImmutable $info_refreshed_at
 * @property CarbonImmutable|null $steam_details_at
 * @property string|null $metacritic_slug
 * @property int|null $metacritic_score
 * @property int|null $metacritic_reviews
 * @property CarbonImmutable|null $metacritic_checked_at
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /** Zdroj hodnocení kritiků z Metacritic v `reviews` (ITAD). */
    public const METASCORE_SOURCE = 'Metascore';

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
        'genres',
        'modes',
        'reviews',
        'boxart_url',
        'banner_url',
        'itad_url',
        'info_refreshed_at',
        'steam_details_at',
        'metacritic_slug',
        'metacritic_score',
        'metacritic_reviews',
        'metacritic_checked_at',
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
            'genres' => 'array',
            'modes' => 'array',
            'reviews' => 'array',
            'info_refreshed_at' => 'immutable_datetime',
            'steam_details_at' => 'immutable_datetime',
            'metacritic_score' => 'integer',
            'metacritic_reviews' => 'integer',
            'metacritic_checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Režimy hry (R25) — ze Steamu a z počtu hráčů připojených produktů PS Store
     * (hra musí mít načtenou vazbu storeListings). PS Store „1 hráč“ znamená
     * hru pro jednoho, víc hráčů u jedné konzole hru u jedné obrazovky.
     *
     * @return list<GameMode>
     */
    public function playModes(): array
    {
        $modes = array_values(array_filter(array_map(GameMode::tryFrom(...), $this->modes ?? [])));

        foreach ($this->storeListings as $listing) {
            if ($listing->local_players === 1) {
                $modes[] = GameMode::Single;
            } elseif ($listing->local_players !== null && $listing->local_players > 1) {
                $modes[] = GameMode::LocalMulti;
            }

            if ($listing->online_players !== null && $listing->online_players > 1) {
                $modes[] = GameMode::Multi;
            }
        }

        $unique = [];
        foreach ($modes as $mode) {
            $unique[$mode->value] = $mode;
        }

        return array_values($unique);
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
     * Produkty v obchodech bez ITAD, připojené odkazem (PlayStation Store, R20).
     *
     * @return HasMany<StoreListing, $this>
     */
    public function storeListings(): HasMany
    {
        return $this->hasMany(StoreListing::class);
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
     * Hodnocení kritiků z Metacritic (R32) — z ITAD, a když ho ITAD nemá
     * (exkluzivity PS5), přímo z Metacritic (R33); null, když ho nemá ani jeden.
     */
    public function metascore(): ?int
    {
        return $this->itadMetascore() ?? $this->metacritic_score;
    }

    /**
     * Metascore ze snímku údajů ITAD (`reviews`); null, když ho ITAD nedává.
     */
    public function itadMetascore(): ?int
    {
        foreach ($this->reviews ?? [] as $review) {
            if ($review['source'] === self::METASCORE_SOURCE) {
                return $review['score'];
            }
        }

        return null;
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
