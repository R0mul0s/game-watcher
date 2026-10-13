<?php

/**
 * Filtry záložky Nadcházející na stránce Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Discover;

use App\Enums\Platform;
use App\Enums\UpcomingSort;

/**
 * Stav je v adrese (`?platform=playstation&coop=1`) jako u přehledu (R21);
 * ověřuje ho UpcomingRequest. Filtruje server nad tabulkou upcoming_releases (R46).
 */
final readonly class UpcomingFilters
{
    /**
     * @param  Platform|null  $platform  jen hry vycházející na platformě; null = všechny
     * @param  list<string>  $genres  žánry IGDB (stačí jeden z nich); prázdné = všechny hry
     * @param  bool  $coop  jen hry pro víc hráčů spolu
     */
    public function __construct(
        public ?Platform $platform = null,
        public array $genres = [],
        public bool $coop = false,
        public UpcomingSort $sort = UpcomingSort::DEFAULT,
    ) {}

    /**
     * Stav pro stránku.
     *
     * @return array{platform: string|null, genres: list<string>, coop: bool, sort: string}
     */
    public function toArray(): array
    {
        return ['platform' => $this->platform?->value, 'genres' => $this->genres, 'coop' => $this->coop, 'sort' => $this->sort->value];
    }
}
