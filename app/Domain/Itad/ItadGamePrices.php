<?php

/**
 * Aktuální ceny jedné hry podle IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Pricing\Money;

/**
 * Položka odpovědi `games/prices` — nabídky obchodů v zemi z konfigurace
 * (games.sources.itad.country) a historické minimum oficiálních obchodů.
 */
final readonly class ItadGamePrices
{
    /**
     * @param  list<ItadDeal>  $deals
     */
    public function __construct(
        public string $itadId,
        public array $deals,
        public ?Money $historyLow,
    ) {}

    /**
     * Ceny z JSON objektu; bez ID hry null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        if (! is_string($data['id'] ?? null)) {
            return null;
        }

        $deals = is_array($data['deals'] ?? null) ? $data['deals'] : [];
        $historyLow = is_array($data['historyLow'] ?? null) ? $data['historyLow'] : [];

        return new self(
            itadId: $data['id'],
            deals: array_values(array_filter(array_map(
                fn (mixed $deal): ?ItadDeal => is_array($deal) ? ItadDeal::fromApi($deal) : null,
                $deals,
            ))),
            historyLow: ItadDeal::money($historyLow['all'] ?? null),
        );
    }
}
