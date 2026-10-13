<?php

/**
 * Produkt obchodu s krabicovými hrami.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail;

/**
 * Product ze schema.org stránky produktu (R52) s nabídkami.
 */
final readonly class RetailProduct
{
    /**
     * @param  list<RetailOffer>  $offers
     */
    public function __construct(
        public string $name,
        public ?string $gtin,
        public array $offers,
    ) {}

    /**
     * Nejlevnější nabídka, která se počítá: nový kus, jde objednat, prodejce
     * z ČR (zvolil uživatel); null, když žádná taková není (vyprodáno).
     */
    public function bestOffer(): ?RetailOffer
    {
        $best = null;
        foreach ($this->offers as $offer) {
            if (! $offer->isNew() || ! $offer->isAvailable() || $offer->isForeign()) {
                continue;
            }

            if ($best === null || $offer->price->cents < $best->price->cents) {
                $best = $offer;
            }
        }

        return $best;
    }
}
