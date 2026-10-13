<?php

/**
 * Filtry přehledu sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Watchlist;

use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceFilter;
use App\Enums\Platform;
use App\Enums\PlayerFilter;
use App\Enums\PriceBasis;
use App\Enums\ReleaseFilter;
use App\Enums\WatchlistSort;

/**
 * Stav filtrů je v adrese (`?platform=playstation&deals=1…`) — jde sdílet
 * a vrátit se k němu (R21). Ověřuje ho DashboardRequest, sem jdou jen platné
 * hodnoty. Cena je v měně zobrazení účtu. Žánr a počet hráčů (R25) jsou
 * údaje hry, ne ceny; stejně tak vydání (R41).
 */
final readonly class WatchlistFilters
{
    /**
     * @param  list<string>|null  $genres  žánry (stačí jeden z nich); null = všechny
     */
    public function __construct(
        public string $query = '',
        public ?Platform $platform = null,
        public ?array $genres = null,
        public ?PlayerFilter $players = null,
        public bool $dealsOnly = false,
        public ?int $maxPriceCents = null,
        public ?int $minCut = null,
        public ?string $type = null,
        public ?ReleaseFilter $release = null,
        public WatchlistSort $sort = WatchlistSort::DEFAULT,
        public bool $withPlus = false,
        public PriceBasis $basis = PriceBasis::Official,
    ) {}

    /**
     * Které ceny se počítají do „nejlevněji“.
     */
    public function priceFilter(): PriceFilter
    {
        return new PriceFilter($this->platform, $this->withPlus, $this->basis);
    }

    /**
     * Omezuje filtr platformu nebo cenu — hra bez ceny pak vypadne?
     */
    public function requiresPrice(): bool
    {
        return $this->platform !== null || $this->dealsOnly
            || $this->maxPriceCents !== null || $this->minCut !== null;
    }

    /**
     * Je nějaký filtr zapnutý (kromě řazení, PS Plus a zdroje ceny)?
     */
    public function isActive(): bool
    {
        return $this->query !== '' || $this->type !== null || $this->release !== null || $this->genres !== null
            || $this->players !== null || $this->requiresPrice();
    }

    /**
     * Stav pro stránku — hodnoty formuláře filtrů.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'platform' => $this->platform?->value,
            'genres' => $this->genres,
            'players' => $this->players?->value,
            'deals' => $this->dealsOnly,
            'maxPrice' => $this->maxPriceCents === null ? null : $this->maxPriceCents / Money::SUBUNITS,
            'minCut' => $this->minCut,
            'type' => $this->type,
            'release' => $this->release?->value,
            'sort' => $this->sort->value,
            'plus' => $this->withPlus,
            'price' => $this->basis->value,
            'active' => $this->isActive(),
        ];
    }
}
