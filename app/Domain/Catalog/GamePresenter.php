<?php

/**
 * Data her pro Inertia stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Itad\ItadGame;
use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceBoard;
use App\Domain\Pricing\PriceFilter;
use App\Enums\GameMode;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\StoreListing;
use App\Models\WatchlistItem;

/**
 * Jedno místo, kde se z hry stává tvar pro Vue — komponenty nic nepočítají
 * (docs/CODING_GUIDELINES.md, sekce 5). Datum vydání jde jako Y-m-d, formát
 * dělá prohlížeč (Intl). Ceny chystá PriceBoard v měně zobrazení účtu (R14) —
 * hra musí mít načtené vazby currentPrices a priceLows (detail i storeListings).
 */
final class GamePresenter
{
    private const DATE_FORMAT = 'Y-m-d';

    /** Hodnocení od této hodnoty (0–100) je kladné — mez „Mostly Positive“ na Steamu. */
    private const REVIEW_GOOD_FROM = 70;

    /** Hodnocení od této hodnoty je smíšené, pod ní záporné (Steam „Mixed“). */
    private const REVIEW_MIXED_FROM = 40;

    public function __construct(
        private readonly PriceBoard $priceBoard,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Karta hry na přehledu sledovaných — s nejlevnější cenou. Hra hlídaná jen
     * na PlayStationu bez připojeného PS Store vyzve k připojení (R24).
     *
     * @return array<string, mixed>
     */
    public function card(Game $game, string $currency, PriceFilter $filter, ?Platform $watchPlatform = null): array
    {
        return [
            ...$this->basics($game),
            'isMature' => $game->is_mature,
            'prices' => $this->priceBoard->summary($game, $currency, $filter),
            'watchPlatform' => $watchPlatform?->value,
            'needsPlayStation' => $watchPlatform === Platform::PlayStation && $game->storeListings->isEmpty(),
        ];
    }

    /**
     * Detail hry s cenami a akcí sledovat / přestat sledovat; ukazuje všechny
     * obchody, hlídaná platforma (R24) se jen zvýrazní.
     *
     * @param  WatchlistItem|null  $watch  položka seznamu, null = hru uživatel nesleduje
     * @return array<string, mixed>
     */
    public function detail(Game $game, ?WatchlistItem $watch, string $currency): array
    {
        return [
            ...$this->basics($game),
            'prices' => $this->priceBoard->detail($game, $currency),
            'watchPlatform' => $watch?->platform?->value,
            'watchPlatformUrl' => route('watchlist.update', $game, absolute: false),
            'itadId' => $game->itad_id,
            'boxartUrl' => $game->boxart_url,
            'isMature' => $game->is_mature,
            'steamAppId' => $game->steam_app_id,
            'developers' => $game->developers ?? [],
            'publishers' => $game->publishers ?? [],
            'tags' => $game->tags ?? [],
            // Žánry ze Steamu a režimy ze Steamu a PS Store (R25)
            'genres' => $game->genres ?? [],
            'modes' => $this->modes($game),
            'reviews' => array_map(fn (array $review): array => [
                ...$review,
                'level' => $this->reviewLevel($review['score']),
            ], $game->reviews ?? []),
            'steamUrl' => $game->steamUrl(),
            'itadUrl' => $game->itad_url,
            'watching' => $watch !== null,
            'unwatchUrl' => route('watchlist.destroy', $game, absolute: false),
            // Produkty PlayStation Store připojené odkazem (R20)
            'listings' => $game->storeListings->map(fn (StoreListing $listing): array => [
                'id' => $listing->id,
                'name' => $listing->name,
                'platforms' => $listing->platforms,
                'url' => $listing->url,
                'removeUrl' => route('listings.destroy', ['game' => $game, 'storeListing' => $listing], absolute: false),
            ])->values()->all(),
            'connectUrl' => route('listings.store', $game, absolute: false),
        ];
    }

    /**
     * Výsledek hledání — odkaz na detail jen u hry, kterou už katalog má; obchody
     * a nejnižší cena z DescribeAvailability (R23). Na PlayStationu je hra
     * s připojeným PS Store (hra musí mít načtenou vazbu storeListings) nebo se
     * stránkou na playstation.com (R24) — tu „Jen na PS“ rovnou připojí.
     *
     * @param  array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}|null  $availability  null = nezjištěno
     * @param  string|null  $playstationPage  stránka hry na www.playstation.com, když existuje
     * @return array<string, mixed>
     */
    public function searchResult(ItadGame $game, ?Game $inCatalog, bool $watching, ?array $availability, ?string $playstationPage, string $currency): array
    {
        $shops = $availability === null ? [] : $this->highlightedShops($availability['shops']);
        $connected = $inCatalog !== null && $inCatalog->storeListings->isNotEmpty();
        $onPlayStation = $connected || $playstationPage !== null;
        $best = $availability === null || $availability['bestCents'] === null || $availability['bestCurrency'] === null
            ? null
            : new Money($availability['bestCents'], $availability['bestCurrency']);

        return [
            'itadId' => $game->id,
            'title' => $game->title,
            'type' => $game->type,
            // Exkluzivity PlayStationu nemají v ITAD obrázek — z katalogu (doplněný z PS Store, R22)
            'thumbnailUrl' => $game->thumbnailUrl ?? $inCatalog?->banner_url,
            'isMature' => $game->mature,
            'watching' => $watching,
            'detailUrl' => $inCatalog === null ? null : route('games.show', $inCatalog, absolute: false),
            'availabilityKnown' => $availability !== null,
            'shops' => $shops,
            'moreShops' => $availability === null ? 0 : count($availability['shops']) - count($shops),
            'bestPrice' => $best === null ? null : ($this->rates->convert($best, $currency) ?? $best)->toArray(),
            // Hra zdarma (free-to-play) — místo „od 0 Kč“
            'free' => $best !== null && $best->cents === 0,
            'onPlayStation' => $onPlayStation,
            // „Jen na PS“ jen tam, kde PlayStation dává smysl (R24) — hra na
            // PlayStationu nebo bez obchodů pro PC (exkluzivita bez stránky)
            'offerPlayStation' => $onPlayStation || ($availability !== null && $availability['shops'] === []),
            // Odkaz, který se při „Jen na PS“ připojí — jen když hra PS Store ještě nemá
            'playstationUrl' => $connected ? null : $playstationPage,
        ];
    }

    /**
     * Obchody k zobrazení — nejdřív hlavní obchody v pořadí z konfigurace,
     * pak ostatní, nejvýš games.catalog.max_shop_badges.
     *
     * @param  list<string>  $shops
     * @return list<string>
     */
    private function highlightedShops(array $shops): array
    {
        /** @var list<string> $priority */
        $priority = config('games.catalog.highlight_shops');
        $rank = fn (string $shop): int => ($index = array_search($shop, $priority, true)) === false ? count($priority) : (int) $index;

        usort($shops, fn (string $a, string $b): int => $rank($a) <=> $rank($b) ?: strcmp($a, $b));

        return array_slice($shops, 0, (int) config('games.catalog.max_shop_badges'));
    }

    /**
     * Údaje hry společné pro kartu i detail.
     *
     * @return array{slug: string, title: string, type: string, bannerUrl: ?string, releaseDate: ?string, url: string}
     */
    private function basics(Game $game): array
    {
        return [
            'slug' => $game->slug,
            'title' => $game->title,
            'type' => $game->type,
            'bannerUrl' => $game->banner_url ?? $game->boxart_url,
            'releaseDate' => $game->release_date?->format(self::DATE_FORMAT),
            'url' => route('games.show', $game, absolute: false),
        ];
    }

    /**
     * Režimy hry v pořadí výčtu (pro jednoho, pro víc, co-op…), ať je výpis
     * na detailu vždy stejně seřazený.
     *
     * @return list<string>
     */
    private function modes(Game $game): array
    {
        $played = $game->playModes();

        return array_values(array_map(
            fn (GameMode $mode): string => $mode->value,
            array_filter(GameMode::cases(), fn (GameMode $mode): bool => in_array($mode, $played, true)),
        ));
    }

    /**
     * Úroveň hodnocení pro barvu skóre: good / mixed / bad.
     */
    private function reviewLevel(int $score): string
    {
        return match (true) {
            $score >= self::REVIEW_GOOD_FROM => 'good',
            $score >= self::REVIEW_MIXED_FROM => 'mixed',
            default => 'bad',
        };
    }
}
