<?php

/**
 * Propojení hry s produktem v obchodě bez ITAD.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\CzechSupport;
use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Produkt se připojuje vložením odkazu (R10, R20); jeho cena je v current_prices
 * pod klíčem obchodu psn-<id propojení>, krabicové hry z Xzone a Heureky (R52)
 * pod xzone-<id> a heureka-<id>.
 *
 * @property int $id
 * @property int $game_id
 * @property PriceSource $source
 * @property string $external_id
 * @property string $name
 * @property string|null $platforms
 * @property int|null $local_players
 * @property int|null $online_players
 * @property string|null $star_rating
 * @property int|null $star_ratings_count
 * @property CzechSupport|null $czech
 * @property string $url
 */
class StoreListing extends Model
{
    /** Předpona klíče obchodu v current_prices (psn-12). */
    public const PLAYSTATION_KEY_PREFIX = 'psn-';

    /** Předpony klíčů krabicových her (R52). */
    private const XZONE_KEY_PREFIX = 'xzone-';

    private const HEUREKA_KEY_PREFIX = 'heureka-';

    /** Všechny předpony klíčů propojených produktů. */
    private const KEY_PREFIXES = [self::PLAYSTATION_KEY_PREFIX, self::XZONE_KEY_PREFIX, self::HEUREKA_KEY_PREFIX];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'source',
        'external_id',
        'name',
        'platforms',
        'local_players',
        'online_players',
        'star_rating',
        'star_ratings_count',
        'czech',
        'url',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PriceSource::class,
            'local_players' => 'integer',
            'online_players' => 'integer',
            'star_rating' => 'decimal:2',
            'star_ratings_count' => 'integer',
            'czech' => CzechSupport::class,
        ];
    }

    /**
     * Hra, ke které produkt patří.
     *
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Klíč obchodu v current_prices a price_history — předpona podle obchodu.
     */
    public function shopKey(): string
    {
        return match ($this->source) {
            PriceSource::PlayStation => self::PLAYSTATION_KEY_PREFIX,
            PriceSource::Xzone => self::XZONE_KEY_PREFIX,
            PriceSource::Heureka => self::HEUREKA_KEY_PREFIX,
            default => throw new LogicException("Obchod {$this->source->value} se nepřipojuje odkazem."),
        }.$this->id;
    }

    /**
     * Je klíč obchodu cenou propojeného produktu? Všechny jsou pro PlayStation
     * (PS Store i krabicové hry na PS5) — podle klíče se pozná platforma historie.
     */
    public static function isListingKey(string $shopKey): bool
    {
        foreach (self::KEY_PREFIXES as $prefix) {
            if (str_starts_with($shopKey, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
