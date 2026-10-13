<?php

/**
 * Aktivní bundle podle IsThereAnyDeal.
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
 * Položka seznamu bundles/v1 (R51). Bundle má úrovně (tiers) s cenou; hra je
 * jen v nejlevnější úrovni, která ji odemyká. Cena úrovně bývá v USD i EUR
 * (i v zemi CZ), u „Build your own“ (Fanatical) chybí. Odkaz `url` je
 * partnerský odkaz ITAD — podle podmínek ITAD se nesmí upravovat.
 */
final readonly class ItadBundle
{
    /**
     * @param  array<string, Money|null>  $games  cena nejlevnější úrovně s hrou podle ITAD ID hry
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $shop,
        public string $url,
        public ?CarbonImmutable $expiresAt,
        public array $games,
    ) {}

    /**
     * Bundle z API; null, když chybí ID, název, obchod nebo odkaz.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $shop = is_array($data['page'] ?? null) ? ($data['page']['name'] ?? null) : null;
        if (! is_int($data['id'] ?? null) || ! is_string($data['title'] ?? null) || ! is_string($shop) || ! is_string($data['url'] ?? null)) {
            return null;
        }

        $games = [];
        foreach (is_array($data['tiers'] ?? null) ? $data['tiers'] : [] as $tier) {
            if (! is_array($tier) || ! is_array($tier['games'] ?? null)) {
                continue;
            }

            $price = ItadDeal::money($tier['price'] ?? null);
            foreach ($tier['games'] as $game) {
                $id = is_array($game) ? ($game['id'] ?? null) : null;
                // Nejlevnější úroveň s hrou — první, která ji uvádí
                if (is_string($id) && ! array_key_exists($id, $games)) {
                    $games[$id] = $price;
                }
            }
        }

        $expiry = $data['expiry'] ?? null;

        return new self(
            id: $data['id'],
            title: $data['title'],
            shop: $shop,
            url: $data['url'],
            expiresAt: is_string($expiry) && strtotime($expiry) !== false ? CarbonImmutable::parse($expiry)->utc() : null,
            games: $games,
        );
    }
}
