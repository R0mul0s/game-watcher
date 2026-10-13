<?php

/**
 * Uložení stažených cen hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\GgDeals\GgDealsPrices;
use App\Domain\Itad\ItadDeal;
use App\Domain\Itad\ItadGamePrices;
use App\Domain\PlayStation\PlayStationProduct;
use App\Enums\Platform;
use App\Enums\PriceScope;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Nabídky jednoho zdroje pro hru se při každém stažení nahradí celé — obchod,
 * který hru přestal nabízet, zmizí. Do `price_history` jde nejnižší cena dne
 * po obchodech (podklad grafu, etapa 7). Částky zůstávají v měně zdroje (R14).
 */
final class StorePrices
{
    /** Nejvýš tolik znaků DRM se uloží (sloupec drm). */
    private const DRM_MAX_LENGTH = 64;

    private const DRM_SEPARATOR = ', ';

    /** Název řádku gg.deals — v UI se ukazuje přeložený text, tohle je jen záznam zdroje. */
    private const GGDEALS_SHOP_NAME = 'gg.deals';

    private const PLAYSTATION_SHOP_NAME = 'PlayStation Store';

    /** Nejvýš tolik znaků názvu edice (sloupec edition). */
    private const EDITION_MAX_LENGTH = 160;

    /**
     * Uloží nabídky oficiálních obchodů a historické minimum z ITAD.
     */
    public function storeItad(Game $game, ItadGamePrices $prices, CarbonImmutable $fetchedAt): void
    {
        $rows = array_map(fn (ItadDeal $deal): array => [
            'game_id' => $game->id,
            'source' => PriceSource::Itad->value,
            'shop_key' => CurrentPrice::ITAD_KEY_PREFIX.$deal->shopId,
            'shop_name' => $deal->shopName,
            'platform' => Platform::Pc->value,
            'edition' => null,
            'amount' => $deal->price->toDecimal(),
            'regular_amount' => $deal->regular?->toDecimal(),
            'member_amount' => null,
            'member_included' => false,
            'member_tier' => null,
            'member_free' => false,
            'member_leaves_at' => null,
            'currency' => $deal->price->currency,
            'cut' => max(0, $deal->cut),
            'drm' => $deal->drm === [] ? null : mb_substr(implode(self::DRM_SEPARATOR, $deal->drm), 0, self::DRM_MAX_LENGTH),
            'url' => $deal->url,
            'expires_at' => $deal->expiresAt,
            'fetched_at' => $fetchedAt,
        ], $prices->deals);

        DB::transaction(function () use ($game, $rows, $prices, $fetchedAt): void {
            $this->replaceSource($game, PriceSource::Itad, $rows);
            $this->storeLow($game, PriceScope::Official, $prices->historyLow, $fetchedAt);
            $this->recordHistory($game, $rows, $fetchedAt);
        });
    }

    /**
     * Uloží nejnižší cenu obchodů s klíči a její historické minimum z gg.deals.
     */
    public function storeGgDeals(Game $game, GgDealsPrices $prices, CarbonImmutable $fetchedAt): void
    {
        $rows = $prices->currentKeyshops === null ? [] : [[
            'game_id' => $game->id,
            'source' => PriceSource::GgDeals->value,
            'shop_key' => CurrentPrice::KEYSHOPS_KEY,
            'shop_name' => self::GGDEALS_SHOP_NAME,
            'platform' => Platform::Pc->value,
            'edition' => null,
            'amount' => $prices->currentKeyshops->toDecimal(),
            'regular_amount' => null,
            'member_amount' => null,
            'member_included' => false,
            'member_tier' => null,
            'member_free' => false,
            'member_leaves_at' => null,
            'currency' => $prices->currentKeyshops->currency,
            'cut' => 0,
            'drm' => null,
            'url' => $prices->url,
            'expires_at' => null,
            'fetched_at' => $fetchedAt,
        ]];

        DB::transaction(function () use ($game, $rows, $prices, $fetchedAt): void {
            $this->replaceSource($game, PriceSource::GgDeals, $rows);
            $this->storeLow($game, PriceScope::Keyshops, $prices->historicalKeyshops, $fetchedAt);
            $this->recordHistory($game, $rows, $fetchedAt);
        });
    }

    /**
     * Uloží cenu produktu PlayStation Store jako samostatnou položku výpisu (R20) —
     * běžnou cenu, cenu před slevou, cenu s PS Plus a zařazení do katalogu PS Plus.
     * Produkt, který se teď koupit nedá, z cen zmizí.
     */
    public function storePlayStation(StoreListing $listing, PlayStationProduct $product, CarbonImmutable $fetchedAt): void
    {
        $game = $listing->game()->sole();
        $price = $product->price;
        if ($price === null) {
            $this->forgetListing($listing);

            return;
        }

        $row = [
            'game_id' => $listing->game_id,
            'source' => PriceSource::PlayStation->value,
            'shop_key' => $listing->shopKey(),
            'shop_name' => self::PLAYSTATION_SHOP_NAME,
            'platform' => Platform::PlayStation->value,
            'edition' => mb_substr($product->name, 0, self::EDITION_MAX_LENGTH),
            'amount' => $price->toDecimal(),
            'regular_amount' => $product->basePrice?->toDecimal(),
            'member_amount' => $product->plusPrice?->toDecimal(),
            'member_included' => $product->inPlusCatalog,
            // Úroveň katalogu a měsíční hra PS Plus (R35)
            'member_tier' => $product->catalogTier?->value,
            'member_free' => $product->plusFree,
            // Odchod z katalogu PS Plus (R49)
            'member_leaves_at' => $product->inPlusCatalog ? $product->catalogLeavesAt : null,
            'currency' => $price->currency,
            'cut' => $product->cut(),
            'drm' => $product->platforms === [] ? null : mb_substr(implode(self::DRM_SEPARATOR, $product->platforms), 0, self::DRM_MAX_LENGTH),
            'url' => $listing->url,
            'expires_at' => $product->cut() > 0 ? $product->saleEndsAt : null,
            'fetched_at' => $fetchedAt,
        ];

        DB::transaction(function () use ($game, $row, $fetchedAt): void {
            CurrentPrice::query()->upsert([$row], ['game_id', 'shop_key'], array_keys(array_diff_key($row, array_flip(['game_id', 'shop_key']))));
            $this->recordHistory($game, [$row], $fetchedAt);
        });
    }

    /**
     * Smaže cenu i historii propojeného produktu (produkt odpojený nebo neprodejný).
     */
    public function forgetListing(StoreListing $listing): void
    {
        CurrentPrice::query()->where('game_id', $listing->game_id)->where('shop_key', $listing->shopKey())->delete();
    }

    /**
     * Nahradí nabídky zdroje pro hru — nové vloží nebo aktualizuje, chybějící smaže.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function replaceSource(Game $game, PriceSource $source, array $rows): void
    {
        CurrentPrice::query()
            ->where('game_id', $game->id)
            ->where('source', $source->value)
            ->whereNotIn('shop_key', array_column($rows, 'shop_key'))
            ->delete();

        if ($rows !== []) {
            CurrentPrice::query()->upsert($rows, ['game_id', 'shop_key'], array_keys(array_diff_key($rows[0], array_flip(['game_id', 'shop_key']))));
        }
    }

    /**
     * Uloží historické minimum, nebo ho smaže, když ho zdroj už neuvádí.
     */
    private function storeLow(Game $game, PriceScope $scope, ?Money $low, CarbonImmutable $fetchedAt): void
    {
        if ($low === null) {
            PriceLow::query()->where('game_id', $game->id)->where('scope', $scope->value)->delete();

            return;
        }

        PriceLow::query()->upsert([[
            'game_id' => $game->id,
            'scope' => $scope->value,
            'amount' => $low->toDecimal(),
            'currency' => $low->currency,
            'fetched_at' => $fetchedAt,
        ]], ['game_id', 'scope'], ['amount', 'currency', 'fetched_at']);
    }

    /**
     * Zapíše ceny do denní historie — za den se drží nejnižší cena obchodu.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function recordHistory(Game $game, array $rows, CarbonImmutable $fetchedAt): void
    {
        if ($rows === []) {
            return;
        }

        $day = $fetchedAt->setTimezone((string) config('games.display_timezone'))->toDateString();
        $placeholders = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?, ?)'));
        $bindings = [];
        foreach ($rows as $row) {
            array_push($bindings, $game->id, $row['shop_key'], $day, $row['amount'], $row['currency']);
        }

        // Upsert s LEAST — query builder Laravelu umí při kolizi jen přepsat hodnotu
        DB::statement(
            "INSERT INTO price_history (game_id, shop_key, day, amount, currency) VALUES {$placeholders}
             ON DUPLICATE KEY UPDATE amount = LEAST(amount, VALUES(amount)), currency = VALUES(currency)",
            $bindings,
        );
    }
}
