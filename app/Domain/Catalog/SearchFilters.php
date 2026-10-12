<?php

/**
 * Filtry výsledků hledání hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Enums\Platform;
use App\Enums\SearchSort;

/**
 * Stav je v adrese (`?q=…&type=game&platform=pc&sort=price&more=1`) jako filtry
 * přehledu (R21, R30). Ověřuje ho GameSearchRequest, sem jdou jen platné hodnoty.
 */
final readonly class SearchFilters
{
    /** Druh „hra“ z ITAD — edice (Deluxe, GOTY) jsou v ITAD také hry. */
    public const TYPE_GAME = 'game';

    /** Vše kromě her — DLC, balíčky a další druhy ITAD. */
    public const TYPE_OTHER = 'other';

    public const TYPES = [self::TYPE_GAME, self::TYPE_OTHER];

    public function __construct(
        public ?string $type = null,
        public ?Platform $platform = null,
        public SearchSort $sort = SearchSort::Relevance,
        public bool $more = false,
    ) {}

    /**
     * Kolik výsledků chtít od ITAD — víc až na „Zobrazit víc“ (každý výsledek
     * stojí ověření stránky na playstation.com, R24).
     */
    public function limit(): int
    {
        return (int) config($this->more ? 'games.catalog.search_results_more' : 'games.catalog.search_results');
    }

    /**
     * Patří hra daného druhu ITAD do výsledků?
     */
    public function acceptsType(string $type): bool
    {
        return match ($this->type) {
            self::TYPE_GAME => $type === self::TYPE_GAME,
            self::TYPE_OTHER => $type !== self::TYPE_GAME,
            default => true,
        };
    }

    /**
     * Stav pro stránku — hodnoty formuláře filtrů.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'platform' => $this->platform?->value,
            'sort' => $this->sort->value,
            'more' => $this->more,
        ];
    }
}
