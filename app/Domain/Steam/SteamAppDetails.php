<?php

/**
 * Žánry a režimy hry ze Steamu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Steam;

use App\Enums\GameMode;

/**
 * Z odpovědi appdetails (filtr categories, genres; jazyk čeština) — žánry
 * jako české názvy, kategorie převedené na režimy hry (R25). Ostatní
 * kategorie (achievementy, podpora ovladače…) se zahazují.
 */
final readonly class SteamAppDetails
{
    /**
     * @param  list<string>  $genres
     * @param  list<GameMode>  $modes
     */
    public function __construct(
        public array $genres,
        public array $modes,
    ) {}

    /**
     * Údaje z části `data` odpovědi appdetails.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $genres = [];
        foreach (is_array($data['genres'] ?? null) ? $data['genres'] : [] as $genre) {
            $name = is_array($genre) && is_string($genre['description'] ?? null) ? trim($genre['description']) : '';
            if ($name !== '') {
                $genres[$name] = $name;
            }
        }

        $categories = GameMode::steamCategories();
        $modes = [];
        foreach (is_array($data['categories'] ?? null) ? $data['categories'] : [] as $category) {
            $mode = is_array($category) && is_int($category['id'] ?? null) ? ($categories[$category['id']] ?? null) : null;
            if ($mode !== null) {
                $modes[$mode->value] = $mode;
            }
        }

        return new self(array_values($genres), array_values($modes));
    }
}
