<?php

/**
 * Továrna testovacích her v katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Údaje mají tvar jako z ITAD (ImportGame); skutečné odpovědi API jsou
 * v tests/Fixtures/itad/.
 *
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /** Rozsah Steam App ID pro náhodné hry. */
    private const MIN_STEAM_APP_ID = 10;

    private const MAX_STEAM_APP_ID = 4000000;

    /** Počet slov náhodného názvu. */
    private const TITLE_WORDS = 3;

    /**
     * Výchozí hodnoty hry.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(self::TITLE_WORDS), '.');
        $itadId = (string) Str::uuid();

        return [
            'itad_id' => $itadId,
            'slug' => Str::slug($title),
            'title' => $title,
            'type' => 'game',
            'is_mature' => false,
            'steam_app_id' => fake()->numberBetween(self::MIN_STEAM_APP_ID, self::MAX_STEAM_APP_ID),
            'release_date' => fake()->date(),
            'developers' => [fake()->company()],
            'publishers' => [fake()->company()],
            'tags' => ['Action'],
            'reviews' => [],
            'boxart_url' => "https://assets.isthereanydeal.com/{$itadId}/boxart.jpg",
            'banner_url' => "https://assets.isthereanydeal.com/{$itadId}/banner600.jpg",
            'itad_url' => 'https://isthereanydeal.com/game/'.Str::slug($title).'/',
            'info_refreshed_at' => CarbonImmutable::now(),
        ];
    }
}
