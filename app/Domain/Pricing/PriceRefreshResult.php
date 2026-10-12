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
 * Kolik her který zdroj aktualizoval a které zdroje selhaly — pro řádek
 * odpovědi cron URL.
 */
final readonly class PriceRefreshResult
{
    /**
     * @param  array<string, int>  $updated  počet her podle zdroje (PriceSource->value)
     * @param  list<PriceSource>  $failed
     */
    public function __construct(
        public array $updated,
        public array $failed,
    ) {}

    /**
     * Počet her, které zdroj aktualizoval.
     */
    public function updatedBy(PriceSource $source): int
    {
        return $this->updated[$source->value] ?? 0;
    }

    /**
     * Selhal aspoň jeden zdroj?
     */
    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
