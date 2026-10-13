<?php

/**
 * Rozbor stránky produktu podle schema.org.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail;

use App\Domain\Pricing\Money;

/**
 * Bloky `<script type="application/ld+json">` (R52) — Product s Offer (Xzone),
 * nebo s AggregateOffer a vnořenými nabídkami prodejců (Heureka). Blok může
 * být objekt, seznam i `@graph`; nevalidní blok se přeskočí. Formát není
 * pod kontrolou projektu — bez produktu vrátí null a nikdy neodhaduje.
 */
final class SchemaProductPage
{
    private const SCRIPT_PATTERN = '~<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>~si';

    private const PRODUCT_TYPE = 'Product';

    /** Předpona hodnot schema.org (https://schema.org/InStock). */
    private const SCHEMA_PREFIX_PATTERN = '~^https?://schema\.org/~i';

    /** Měna cen v obchodech (Heureka přepočítává i nabídky slovenských obchodů). */
    private const CURRENCY = 'CZK';

    /** Desetinná místa ceny („1142.86“ i „1899“ → setiny). */
    private const PRICE_DECIMALS = 2;

    /**
     * Produkt ze stránky; null, když stránka žádný Product nemá.
     */
    public static function product(string $html): ?RetailProduct
    {
        preg_match_all(self::SCRIPT_PATTERN, $html, $matches);
        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);
            foreach (self::items($data) as $item) {
                if (self::isProduct($item) && is_string($item['name'] ?? null)) {
                    return new RetailProduct(
                        name: trim(html_entity_decode($item['name'], ENT_QUOTES)),
                        gtin: self::gtin($item),
                        offers: self::offers($item['offers'] ?? null),
                    );
                }
            }
        }

        return null;
    }

    /**
     * Položky bloku JSON-LD — objekt, seznam nebo `@graph`.
     *
     * @return list<array<mixed>>
     */
    private static function items(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $items = is_array($data['@graph'] ?? null) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * Je položka Product (`@type` řetězec i seznam)?
     *
     * @param  array<mixed>  $item
     */
    private static function isProduct(array $item): bool
    {
        $type = $item['@type'] ?? null;

        return $type === self::PRODUCT_TYPE || (is_array($type) && in_array(self::PRODUCT_TYPE, $type, true));
    }

    /**
     * Nabídky — Offer, seznam Offer, nebo AggregateOffer s vnořenými `offers`.
     *
     * @return list<RetailOffer>
     */
    private static function offers(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (! array_is_list($data) && is_array($data['offers'] ?? null)) {
            return self::offers($data['offers']);
        }

        $offers = [];
        foreach (array_is_list($data) ? $data : [$data] as $offer) {
            $parsed = is_array($offer) ? self::offer($offer) : null;
            if ($parsed !== null) {
                $offers[] = $parsed;
            }
        }

        return $offers;
    }

    /**
     * Jedna nabídka; null bez ceny v CZK.
     *
     * @param  array<mixed>  $offer
     */
    private static function offer(array $offer): ?RetailOffer
    {
        $price = $offer['price'] ?? (is_array($offer['priceSpecification'] ?? null) ? ($offer['priceSpecification']['price'] ?? null) : null);
        $currency = $offer['priceCurrency'] ?? self::CURRENCY;
        if ((! is_string($price) && ! is_int($price) && ! is_float($price)) || ! is_numeric((string) $price) || $currency !== self::CURRENCY) {
            return null;
        }

        $seller = is_array($offer['seller'] ?? null) ? ($offer['seller']['name'] ?? null) : null;

        return new RetailOffer(
            // Řetězec („1142.86“) bez převodu přes float; číslo z JSON už float je
            price: Money::fromDecimal(is_string($price) ? $price : number_format((float) $price, self::PRICE_DECIMALS, '.', ''), self::CURRENCY),
            seller: is_string($seller) && trim($seller) !== '' ? trim(html_entity_decode($seller, ENT_QUOTES)) : null,
            availability: self::schemaValue($offer['availability'] ?? null),
            condition: self::schemaValue($offer['itemCondition'] ?? null),
        );
    }

    /**
     * EAN produktu (gtin13, gtin, gtin8); null, když ho stránka neuvádí.
     *
     * @param  array<mixed>  $item
     */
    private static function gtin(array $item): ?string
    {
        foreach (['gtin13', 'gtin', 'gtin8'] as $field) {
            if (is_string($item[$field] ?? null) && ctype_digit($item[$field])) {
                return $item[$field];
            }
        }

        return null;
    }

    /**
     * Hodnota schema.org bez předpony (https://schema.org/InStock → InStock).
     */
    private static function schemaValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? (string) preg_replace(self::SCHEMA_PREFIX_PATTERN, '', trim($value)) : null;
    }
}
