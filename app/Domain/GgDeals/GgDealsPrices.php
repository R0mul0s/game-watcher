<?php

/**
 * Souhrnné ceny hry podle gg.deals.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\GgDeals;

use App\Domain\Pricing\Money;
use InvalidArgumentException;

/**
 * gg.deals vrací jen nejnižší ceny — oficiálních obchodů a obchodů s klíči,
 * aktuální a historickou (R9). Cenu konkrétního obchodu (G2A, Loaded) nedává.
 * Data se smí zobrazit jen s odkazem na gg.deals (`url`).
 */
final readonly class GgDealsPrices
{
    public function __construct(
        public int $steamAppId,
        public ?Money $currentKeyshops,
        public ?Money $historicalKeyshops,
        public ?string $url,
    ) {}

    /**
     * Ceny z JSON objektu hry; bez objektu `prices` null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(int $steamAppId, array $data): ?self
    {
        $prices = $data['prices'] ?? null;
        if (! is_array($prices) || ! is_string($prices['currency'] ?? null)) {
            return null;
        }

        return new self(
            steamAppId: $steamAppId,
            currentKeyshops: self::money($prices['currentKeyshops'] ?? null, $prices['currency']),
            historicalKeyshops: self::money($prices['historicalKeyshops'] ?? null, $prices['currency']),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
        );
    }

    /**
     * Částka z řetězce „38.98“; chybějící nebo nečíselná hodnota = null.
     */
    private static function money(mixed $amount, string $currency): ?Money
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return null;
        }

        try {
            return Money::fromDecimal((string) $amount, $currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
