<?php

/**
 * Pokles ceny sledované hry za období.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Watchlist;

use App\Domain\Pricing\Money;
use App\Models\Game;

/**
 * Výsledek DailyDeals::drops() — pro slevy dne na přehledu (R26) i týdenní
 * souhrn e-mailem (R36). Obě ceny jsou v měně zobrazení účtu.
 */
final readonly class PriceDrop
{
    public function __construct(
        public Game $game,
        public Money $price,
        public Money $previous,
        public int $drop,
    ) {}
}
