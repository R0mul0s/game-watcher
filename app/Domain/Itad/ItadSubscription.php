<?php

/**
 * Hra v předplatném podle IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use Carbon\CarbonImmutable;

/**
 * Položka `subs` odpovědi games/subs/v1 (R50) — ID a název předplatného
 * (6 = Game Pass, 2 = EA Play…) a kdy hra z předplatného odchází.
 */
final readonly class ItadSubscription
{
    public function __construct(
        public int $id,
        public string $name,
        public ?CarbonImmutable $leavesAt,
    ) {}

    /**
     * Položka z API; null, když nemá ID nebo název.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $leaving = $data['leaving'] ?? null;

        return is_int($data['id'] ?? null) && is_string($data['name'] ?? null)
            ? new self(
                $data['id'],
                $data['name'],
                is_string($leaving) && strtotime($leaving) !== false ? CarbonImmutable::parse($leaving)->utc() : null,
            )
            : null;
    }
}
