<?php

/**
 * Nadcházející hra z IGDB pro záložku Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Platform;
use App\Enums\ReleasePrecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Není to hra katalogu (R10, R46) — řádky celé přepisuje cron z IGDB, ITAD ID
 * se doplní až při sledování. Vydání se vede po platformách.
 *
 * @property int $id
 * @property int $igdb_id
 * @property string $title
 * @property string|null $igdb_url
 * @property int $hypes
 * @property int|null $steam_app_id
 * @property string|null $itad_id
 * @property string|null $cover_image_id
 * @property string|null $banner_image_id
 * @property list<string>|null $genres
 * @property bool $coop
 * @property CarbonImmutable|null $pc_release_on
 * @property ReleasePrecision|null $pc_release_precision
 * @property bool $pc_early_access
 * @property CarbonImmutable|null $playstation_release_on
 * @property ReleasePrecision|null $playstation_release_precision
 * @property bool $playstation_early_access
 * @property CarbonImmutable $next_release_on
 */
class UpcomingRelease extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'igdb_id',
        'title',
        'igdb_url',
        'hypes',
        'steam_app_id',
        'itad_id',
        'cover_image_id',
        'banner_image_id',
        'genres',
        'coop',
        'pc_release_on',
        'pc_release_precision',
        'pc_early_access',
        'playstation_release_on',
        'playstation_release_precision',
        'playstation_early_access',
        'next_release_on',
    ];

    /**
     * Přetypování atributů.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'igdb_id' => 'integer',
            'hypes' => 'integer',
            'steam_app_id' => 'integer',
            'genres' => 'array',
            'coop' => 'boolean',
            'pc_release_on' => 'immutable_date',
            'pc_release_precision' => ReleasePrecision::class,
            'pc_early_access' => 'boolean',
            'playstation_release_on' => 'immutable_date',
            'playstation_release_precision' => ReleasePrecision::class,
            'playstation_early_access' => 'boolean',
            'next_release_on' => 'immutable_date',
        ];
    }

    /**
     * Nadcházející vydání na platformě; null, když tam hra nevychází.
     *
     * @return array{date: CarbonImmutable, precision: ReleasePrecision, earlyAccess: bool}|null
     */
    public function releaseOn(Platform $platform): ?array
    {
        $date = $this->getAttribute($platform->value.'_release_on');
        $precision = $this->getAttribute($platform->value.'_release_precision');
        if (! $date instanceof CarbonImmutable || ! $precision instanceof ReleasePrecision) {
            return null;
        }

        return ['date' => $date, 'precision' => $precision, 'earlyAccess' => (bool) $this->getAttribute($platform->value.'_early_access')];
    }
}
