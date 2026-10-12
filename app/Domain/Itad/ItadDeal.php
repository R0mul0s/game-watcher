<?php

/**
 * Nabídka hry v jednom obchodě podle IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Pricing\Money;
use Carbon\CarbonImmutable;

/**
 * Položka `deals` z `games/prices` — cena, běžná cena, sleva, DRM a odkaz.
 * Odkaz je partnerský odkaz ITAD (itad.link) a podle podmínek API se nesmí měnit.
 */
final readonly class ItadDeal
{
    /**
     * @param  list<string>  $drm
     */
    public function __construct(
        public int $shopId,
        public string $shopName,
        public Money $price,
        public ?Money $regular,
        public int $cut,
        public array $drm,
        public ?string $url,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /**
     * Nabídka z JSON objektu; neúplnou vrátí jako null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $shop = is_array($data['shop'] ?? null) ? $data['shop'] : [];
        $price = self::money($data['price'] ?? null);
        if (! is_int($shop['id'] ?? null) || ! is_string($shop['name'] ?? null) || $price === null) {
            return null;
        }

        $drm = is_array($data['drm'] ?? null) ? $data['drm'] : [];
        $expiry = $data['expiry'] ?? null;

        return new self(
            shopId: $shop['id'],
            shopName: $shop['name'],
            price: $price,
            regular: self::money($data['regular'] ?? null),
            cut: is_int($data['cut'] ?? null) ? $data['cut'] : 0,
            drm: array_values(array_filter(array_map(
                fn (mixed $item): ?string => is_array($item) && is_string($item['name'] ?? null) ? $item['name'] : null,
                $drm,
            ))),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            expiresAt: is_string($expiry) ? CarbonImmutable::parse($expiry)->utc() : null,
        );
    }

    /**
     * Částka z objektu `{amount, amountInt, currency}` — z celých setin, ne z floatu.
     */
    public static function money(mixed $data): ?Money
    {
        if (! is_array($data) || ! is_int($data['amountInt'] ?? null) || ! is_string($data['currency'] ?? null)) {
            return null;
        }

        return new Money($data['amountInt'], $data['currency']);
    }
}
