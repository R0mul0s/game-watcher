<?php

/**
 * Připojení produktu PlayStation Store ke hře.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Actions;

use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Domain\PlayStation\PlayStationClient;
use App\Domain\PlayStation\PlayStationProduct;
use App\Domain\Pricing\StorePrices;
use App\Enums\PriceSource;
use App\Models\Game;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;

/**
 * Uživatel vloží odkaz na produkt nebo stránku hry (koncept) v PS Store, nebo
 * stránku hry na www.playstation.com (R10, R20, R22) — produkt najde
 * FindPlayStationProduct. Jinou edici nebo platformu vybere uživatel odkazem
 * na konkrétní produkt. Cena se stáhne hned.
 */
final class ConnectPlayStationProduct
{
    public function __construct(
        private readonly FindPlayStationProduct $findProduct,
        private readonly PlayStationClient $client,
        private readonly StorePrices $storePrices,
    ) {}

    /**
     * Propojení produktu se hrou; stejný produkt podruhé nic nezdvojí.
     *
     * @throws PlayStationProductNotFound
     * @throws PlayStationUnavailable
     */
    public function handle(Game $game, string $url): StoreListing
    {
        $product = $this->findProduct->handle($url);

        $listing = StoreListing::query()->updateOrCreate(
            ['game_id' => $game->id, 'source' => PriceSource::PlayStation->value, 'external_id' => $product->id],
            [
                'name' => $product->name,
                'platforms' => $product->platforms === [] ? null : implode(', ', $product->platforms),
                'local_players' => $product->localPlayers,
                'online_players' => $product->onlinePlayers,
                'url' => $this->client->productUrl($product->id),
            ],
        );

        $this->storePrices->storePlayStation($listing, $product, CarbonImmutable::now());
        $this->fillMissingDetails($game, $product);

        return $listing;
    }

    /**
     * Doplní hře údaje, které IsThereAnyDeal nemá — exkluzivity PlayStationu jsou
     * v ITAD jen zástupný záznam bez obrázků, data vydání a vydavatele (R22).
     * Údaje z ITAD se nepřepisují.
     */
    private function fillMissingDetails(Game $game, PlayStationProduct $product): void
    {
        $game->fill(array_filter([
            'boxart_url' => $game->boxart_url === null ? $product->coverUrl : null,
            'banner_url' => $game->banner_url === null ? $product->bannerUrl : null,
            'release_date' => $game->release_date === null ? $product->releaseDate : null,
            'publishers' => ($game->publishers ?? []) === [] && $product->publisher !== null ? [$product->publisher] : null,
        ], fn (mixed $value): bool => $value !== null));

        $game->save();
    }
}
