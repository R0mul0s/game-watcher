<?php

/**
 * Výsledek vyhodnocení ceny sledované hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

/**
 * Platí podmínka (cíl, historické minimum) a případné upozornění k odeslání —
 * podmínka může platit i bez upozornění (ta cena už odešla, R26).
 */
final readonly class PriceCheck
{
    public function __construct(
        public bool $met,
        public ?PriceAlert $alert,
    ) {}
}
