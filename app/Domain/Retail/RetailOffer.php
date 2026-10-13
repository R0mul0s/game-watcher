<?php

/**
 * Nabídka krabicové hry v obchodě.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail;

use App\Domain\Pricing\Money;

/**
 * Offer ze schema.org (R52) — u Xzone jediná nabídka obchodu, u Heureky
 * nabídka jednoho z obchodů (prodejce).
 */
final readonly class RetailOffer
{
    /** Dostupnost, se kterou jde kus koupit — skladem, nebo předobjednávka (nevydaná hra). */
    private const AVAILABLE = ['InStock', 'PreOrder'];

    /** Stav nového kusu. */
    private const NEW_CONDITION = 'NewCondition';

    public function __construct(
        public Money $price,
        public ?string $seller,
        // Hodnota schema.org bez předpony (InStock, OutOfStock, InStoreOnly…); null = neuvedeno
        public ?string $availability,
        // Hodnota schema.org bez předpony (NewCondition, UsedCondition…); null = neuvedeno
        public ?string $condition,
    ) {}

    /**
     * Jde kus objednat — skladem nebo předobjednávka? „Jen na prodejně“ ne.
     */
    public function isAvailable(): bool
    {
        return in_array($this->availability, self::AVAILABLE, true);
    }

    /**
     * Nový kus? Bez uvedeného stavu se bere jako nový (obchody bazar uvádějí).
     */
    public function isNew(): bool
    {
        return $this->condition === null || $this->condition === self::NEW_CONDITION;
    }

    /**
     * Prodejce z ciziny podle domény v názvu („Digihry.sk“) — games.sources.retail.foreign_seller_domains.
     */
    public function isForeign(): bool
    {
        if ($this->seller === null) {
            return false;
        }

        foreach ((array) config('games.sources.retail.foreign_seller_domains') as $domain) {
            if (str_ends_with(mb_strtolower(trim($this->seller)), '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
