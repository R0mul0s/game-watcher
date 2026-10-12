<?php

/**
 * Změna ceny v jednom obchodě z historie ITAD.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Pricing\Money;
use Carbon\CarbonImmutable;

/**
 * Položka `games/history/v2` (R28) — od okamžiku `at` stojí hra v obchodě
 * `price`. První záznam obchodu je stav k začátku období. Do cache jde jako
 * pole (toArray), objekty cache neumí (R13 v CLAUDE.md).
 */
final readonly class ItadPriceChange
{
    public function __construct(
        public CarbonImmutable $at,
        public int $shopId,
        public string $shopName,
        public Money $price,
    ) {}

    /**
     * Záznam z JSON objektu; neúplný vrátí jako null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $shop = is_array($data['shop'] ?? null) ? $data['shop'] : [];
        $deal = is_array($data['deal'] ?? null) ? $data['deal'] : [];
        $price = ItadDeal::money($deal['price'] ?? null);
        $timestamp = $data['timestamp'] ?? null;

        if (! is_string($timestamp) || ! is_int($shop['id'] ?? null) || ! is_string($shop['name'] ?? null) || $price === null) {
            return null;
        }

        return new self(CarbonImmutable::parse($timestamp)->utc(), $shop['id'], $shop['name'], $price);
    }

    /**
     * Záznam z cache (toArray).
     *
     * @param  array{at: string, shopId: int, shopName: string, cents: int, currency: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(CarbonImmutable::parse($data['at'])->utc(), $data['shopId'], $data['shopName'], new Money($data['cents'], $data['currency']));
    }

    /**
     * Záznam pro cache.
     *
     * @return array{at: string, shopId: int, shopName: string, cents: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'at' => $this->at->toIso8601String(),
            'shopId' => $this->shopId,
            'shopName' => $this->shopName,
            'cents' => $this->price->cents,
            'currency' => $this->price->currency,
        ];
    }
}
