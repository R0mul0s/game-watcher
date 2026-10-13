<?php

/**
 * Upozornění na cenu jedné sledované hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Pricing\Money;
use App\Enums\AlertReason;
use App\Models\Game;
use App\Models\WatchlistItem;

/**
 * Cena je v měně zobrazení účtu (R14); obchod a odkaz jsou nejlevnější
 * nabídka podle hlídané platformy, včetně obchodů s klíči (R26).
 */
final readonly class PriceAlert
{
    public function __construct(
        public WatchlistItem $item,
        public Game $game,
        public AlertReason $reason,
        public Money $price,
        public string $shop,
        public ?string $url,
        public ?Money $target,
        // Nejlevnější jsou obchody s klíči (souhrn gg.deals), ne jeden obchod
        public bool $keyshops = false,
        // Nabídka je ve slevě — u vydané hry tím odešla i „první sleva“ (R41)
        public bool $discounted = false,
    ) {}
}
