<?php

/**
 * Výsledek stažení cen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Enums\PriceSource;

/**
 * Kolik her který zdroj aktualizoval, kolik jich měl, a které zdroje selhaly
 * s jakou zprávou — pro řádek odpovědi cron URL a stav zdrojů (R29).
 */
final readonly class PriceRefreshResult
{
    /**
     * @param  array<string, int>  $updated  počet her (u PS Store produktů) podle zdroje (PriceSource->value)
     * @param  list<PriceSource>  $failed
     * @param  array<string, int>  $expected  kolik her (produktů) měl zdroj aktualizovat
     * @param  array<string, string>  $errors  zpráva výpadku podle zdroje (bez API klíče)
     */
    public function __construct(
        public array $updated,
        public array $failed,
        public array $expected = [],
        public array $errors = [],
    ) {}

    /**
     * Počet her, které zdroj aktualizoval.
     */
    public function updatedBy(PriceSource $source): int
    {
        return $this->updated[$source->value] ?? 0;
    }

    /**
     * Kolik her (produktů) měl zdroj aktualizovat.
     */
    public function expectedBy(PriceSource $source): int
    {
        return $this->expected[$source->value] ?? 0;
    }

    /**
     * Selhal zdroj?
     */
    public function hasFailed(PriceSource $source): bool
    {
        return in_array($source, $this->failed, true);
    }

    /**
     * Zpráva výpadku zdroje; prázdná, když nic neřekl.
     */
    public function errorOf(PriceSource $source): string
    {
        return $this->errors[$source->value] ?? '';
    }

    /**
     * Selhal aspoň jeden zdroj?
     */
    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
