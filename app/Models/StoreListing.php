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

use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produkt se připojuje vložením odkazu (R10, R20); jeho cena je v current_prices
 * pod klíčem obchodu psn-<id propojení>.
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
 * @property string $url
 */
class StoreListing extends Model
{
    /** Předpona klíče obchodu v current_prices (psn-12). */
    public const PLAYSTATION_KEY_PREFIX = 'psn-';

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
     * Klíč obchodu v current_prices a price_history.
     */
    public function shopKey(): string
    {
        return self::PLAYSTATION_KEY_PREFIX.$this->id;
    }
}
