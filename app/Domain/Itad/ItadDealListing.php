<?php

/**
 * Hra ze seznamu slev ITAD.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Pricing\Money;
use Carbon\CarbonImmutable;

/**
 * Položka `deals/v2` (R43) — hra s nejlepší aktuální slevou napříč obchody.
 * `flag` je značka ITAD: N = nové historické minimum, H = historické minimum,
 * S = minimum obchodu. Do cache jde jako pole (toArray), objekty cache neumí.
 */
final readonly class ItadDealListing
{
    public function __construct(
        public string $itadId,
        public string $title,
        public string $type,
        public ?string $bannerUrl,
        public string $shopName,
        public Money $price,
        public ?Money $regular,
        public int $cut,
        public ?string $flag,
        public ?string $url,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /**
     * Položka z JSON objektu; neúplnou vrátí jako null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $deal = is_array($data['deal'] ?? null) ? $data['deal'] : [];
        $shop = is_array($deal['shop'] ?? null) ? $deal['shop'] : [];
        $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];
        $price = ItadDeal::money($deal['price'] ?? null);

        if (! is_string($data['id'] ?? null) || ! is_string($data['title'] ?? null) || ! is_string($shop['name'] ?? null) || $price === null) {
            return null;
        }

        $banner = $assets['banner400'] ?? $assets['banner300'] ?? $assets['boxart'] ?? null;
        $flag = $deal['flag'] ?? null;
        $url = $deal['url'] ?? null;
        $expiry = $deal['expiry'] ?? null;

        return new self(
            itadId: $data['id'],
            title: $data['title'],
            type: is_string($data['type'] ?? null) ? $data['type'] : '',
            bannerUrl: is_string($banner) ? $banner : null,
            shopName: $shop['name'],
            price: $price,
            regular: ItadDeal::money($deal['regular'] ?? null),
            cut: is_int($deal['cut'] ?? null) ? $deal['cut'] : 0,
            flag: is_string($flag) ? $flag : null,
            url: is_string($url) ? $url : null,
            expiresAt: is_string($expiry) ? CarbonImmutable::parse($expiry)->utc() : null,
        );
    }

    /**
     * Položka z cache (toArray).
     *
     * @param  array{itadId: string, title: string, type: string, bannerUrl: string|null, shopName: string, cents: int, regularCents: int|null, currency: string, cut: int, flag: string|null, url: string|null, expiresAt: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            itadId: $data['itadId'],
            title: $data['title'],
            type: $data['type'],
            bannerUrl: $data['bannerUrl'],
            shopName: $data['shopName'],
            price: new Money($data['cents'], $data['currency']),
            regular: $data['regularCents'] === null ? null : new Money($data['regularCents'], $data['currency']),
            cut: $data['cut'],
            flag: $data['flag'],
            url: $data['url'],
            expiresAt: $data['expiresAt'] === null ? null : CarbonImmutable::parse($data['expiresAt'])->utc(),
        );
    }

    /**
     * Položka pro cache.
     *
     * @return array{itadId: string, title: string, type: string, bannerUrl: string|null, shopName: string, cents: int, regularCents: int|null, currency: string, cut: int, flag: string|null, url: string|null, expiresAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'itadId' => $this->itadId,
            'title' => $this->title,
            'type' => $this->type,
            'bannerUrl' => $this->bannerUrl,
            'shopName' => $this->shopName,
            'cents' => $this->price->cents,
            'regularCents' => $this->regular?->cents,
            'currency' => $this->price->currency,
            'cut' => $this->cut,
            'flag' => $this->flag,
            'url' => $this->url,
            'expiresAt' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
