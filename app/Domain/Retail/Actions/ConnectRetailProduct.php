<?php

/**
 * Připojení krabicové hry z obchodu ke hře.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail\Actions;

use App\Domain\Pricing\StorePrices;
use App\Domain\Retail\Exceptions\RetailFormatChanged;
use App\Domain\Retail\Exceptions\RetailProductNotFound;
use App\Domain\Retail\Exceptions\RetailUnavailable;
use App\Domain\Retail\RetailClient;
use App\Domain\Retail\RetailUrl;
use App\Models\Game;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;

/**
 * Uživatel vloží odkaz na produkt Xzone nebo Heureky (R52) — produkt se
 * nehledá podle názvu (R10), edici a platformu určí odkaz. Cena se stáhne hned.
 */
final class ConnectRetailProduct
{
    /** Nejvýš tolik znaků názvu produktu (store_listings.name). */
    private const NAME_MAX_LENGTH = 160;

    public function __construct(
        private readonly RetailClient $client,
        private readonly StorePrices $storePrices,
    ) {}

    /**
     * Propojení produktu se hrou; stejný produkt podruhé nic nezdvojí.
     *
     * @throws RetailProductNotFound
     * @throws RetailUnavailable
     */
    public function handle(Game $game, RetailUrl $url): StoreListing
    {
        try {
            $product = $this->client->product($url);
        } catch (RetailFormatChanged $e) {
            // Při připojování stránka bez produktu = odkaz na kategorii nebo výpis
            throw new RetailProductNotFound($url->url, previous: $e);
        }

        if ($product === null) {
            throw new RetailProductNotFound($url->url);
        }

        $listing = StoreListing::query()->updateOrCreate(
            ['game_id' => $game->id, 'source' => $url->source->value, 'external_id' => $url->externalId()],
            ['name' => mb_substr($product->name, 0, self::NAME_MAX_LENGTH), 'url' => $url->url],
        );

        $this->storePrices->storeRetail($listing, $product, CarbonImmutable::now());

        return $listing;
    }
}
