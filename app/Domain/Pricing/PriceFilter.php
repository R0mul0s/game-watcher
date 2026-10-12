<?php

/**
 * Které ceny se počítají do „nejlevněji“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Enums\Platform;

/**
 * Platforma z filtru přehledu nebo hlídaná u hry (R21, R24) a volba „mám
 * PS Plus“ — s ní se u PlayStation Store počítá cena pro předplatitele (R20).
 */
final readonly class PriceFilter
{
    public function __construct(
        public ?Platform $platform = null,
        public bool $withPlus = false,
    ) {}

    /**
     * Počítá se nabídka této platformy?
     */
    public function accepts(Platform $platform): bool
    {
        return $this->platform === null || $this->platform === $platform;
    }

    /**
     * Filtr pro hru hlídanou jen na jedné platformě (R24) — platí jen ceny té
     * platformy. Platforma z filtru přehledu má přednost; hru hlídanou jinde
     * přehled s takovým filtrem vůbec neukáže (WatchlistView).
     */
    public function restrictedTo(?Platform $platform): self
    {
        return $platform === null || $this->platform !== null ? $this : new self($platform, $this->withPlus);
    }

    /**
     * Ukazuje se nejnižší cena obchodů s klíči (ty prodávají klíče pro PC)?
     */
    public function acceptsKeyshops(): bool
    {
        return $this->accepts(Platform::Pc);
    }
}
