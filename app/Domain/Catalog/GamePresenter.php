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
use App\Domain\Hltb\HltbUrl;
use App\Domain\Itad\ItadGame;
use App\Domain\Metacritic\MetacriticUrl;
use App\Domain\Pricing\Money;
use App\Domain\Pricing\PriceBoard;
use App\Domain\Pricing\PriceFilter;
use App\Enums\CzechSupport;
use App\Enums\GameMode;
use App\Enums\Platform;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\GameBundle;
use App\Models\StoreListing;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;

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

    private const MINUTES_PER_HOUR = 60;

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
    public function card(Game $game, string $currency, PriceFilter $filter, ?WatchlistItem $item = null): array
    {
        $summary = $this->priceBoard->summary($game, $currency, $filter);
        $watchPlatform = $item?->platform;
        // Cílová cena v měně zobrazení; bez kurzu se neukáže (R26)
        $target = ($itemTarget = $item?->target()) === null ? null : $this->rates->convert($itemTarget, $currency);
        $cheapest = $this->priceBoard->cheapest($summary);
        $ranked = $this->priceBoard->ranked($summary, $filter->basis);

        return [
            ...$this->basics($game),
            'isMature' => $game->is_mature,
            'prices' => $summary,
            // Cena pro řazení a filtry podle volby „Cena“ (R30) — karta ji zvýrazní
            'ranked' => $ranked,
            // Hodnocení kritiků z Metacritic pro kartu a řazení (R32, R33)
            'metascore' => ($metascore = $game->metascore()) === null ? null : [
                'score' => $metascore,
                'level' => $this->reviewLevel($metascore),
            ],
            // Hodnocení hráčů na PS Store — produkt s nejvíc hodnoceními (R33)
            'playerRating' => ($listing = $this->mostRatedListing($game)) === null ? null : [
                'rating' => (float) $listing->star_rating,
                'count' => $listing->star_ratings_count,
            ],
            'watchPlatform' => $watchPlatform?->value,
            'needsPlayStation' => $watchPlatform === Platform::PlayStation && $game->storeListings->isEmpty(),
            'target' => $target?->toArray(),
            // Stejné pravidlo jako upozornění — nejlevnější včetně obchodů s klíči
            'targetReached' => $target !== null && $cheapest !== null && $cheapest['effectiveCents'] <= $target->cents,
            // Hlavní příběh podle HowLongToBeat a cena za hodinu podle volby „Cena“ (R44)
            'playtime' => $game->hltb_main_minutes,
            'pricePerHour' => $this->pricePerHour($game, $ranked, $currency),
            // Čeština ze Steamu nebo PS Store (R47) — štítek jen titulky a dabing
            'czech' => $game->czechSupport()?->value,
            // Nejbližší odchod z katalogu PS Plus (R49) — jen štítek, bez upozornění
            'plusLeavesAt' => $this->plusLeavesAt($game)?->toIso8601String(),
            // Game Pass (R50) a nejlevnější aktivní bundle (R51); hra musí mít načtenou vazbu bundles
            'gamePass' => $game->in_game_pass,
            'bundle' => $this->bundles($game, $currency)[0] ?? null,
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
            // Cílová cena v měně zobrazení (zadaná v jiné měně se převede, R26)
            'target' => ($target = $watch?->target()) === null ? null : ($this->rates->convert($target, $currency) ?? $target)->toArray(),
            'targetUrl' => route('watchlist.target', $game, absolute: false),
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
            'czech' => $this->czech($game),
            'reviews' => array_map(fn (array $review): array => [
                ...$review,
                'level' => $this->reviewLevel($review['score']),
            ], $this->reviews($game)),
            // Hodnocení hráčů na PS Store po připojených produktech (R33)
            'playerRatings' => $game->storeListings
                ->filter(fn (StoreListing $listing): bool => $listing->star_rating !== null)
                ->map(fn (StoreListing $listing): array => [
                    'name' => $listing->name,
                    'rating' => (float) $listing->star_rating,
                    'count' => $listing->star_ratings_count,
                ])->values()->all(),
            // Odkaz na Metacritic od uživatele — jen hra bez Metascore z ITAD (R33)
            'metacritic' => $game->itadMetascore() !== null ? null : [
                'url' => $game->metacritic_slug === null ? null : MetacriticUrl::game($game->metacritic_slug),
                'checked' => $game->metacritic_checked_at !== null,
                'linkUrl' => route('metacritic.update', $game, absolute: false),
            ],
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
            // Game Pass (R50) a aktivní bundly (R51) z ITAD
            'gamePass' => $game->in_game_pass ? ['leavesAt' => $game->game_pass_leaves_at?->toIso8601String()] : null,
            'bundles' => $this->bundles($game, $currency),
            // Délka hraní z HowLongToBeat (R44) — cena za hodinu z nejlevnější
            // oficiální nabídky hlídané platformy, odkaz pro hru, kterou hledání nenašlo
            'playtime' => [
                'main' => $game->hltb_main_minutes,
                'extra' => $game->hltb_extra_minutes,
                'complete' => $game->hltb_complete_minutes,
                'url' => $game->hltb_id === null ? null : HltbUrl::game($game->hltb_id),
                'checked' => $game->hltb_checked_at !== null,
                'linkUrl' => route('hltb.update', $game, absolute: false),
                'pricePerHour' => $this->pricePerHour(
                    $game,
                    $this->priceBoard->summary($game, $currency, (new PriceFilter)->restrictedTo($watch?->platform))['best'],
                    $currency,
                ),
            ],
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
     * @return array{slug: string, title: string, type: string, bannerUrl: ?string, releaseDate: ?string, releaseInDays: ?int, url: string}
     */
    private function basics(Game $game): array
    {
        return [
            'slug' => $game->slug,
            'title' => $game->title,
            'type' => $game->type,
            'bannerUrl' => $game->banner_url ?? $game->boxart_url,
            'releaseDate' => $game->release_date?->format(self::DATE_FORMAT),
            // Odpočet do vydání (R41); null = vyšla nebo datum neznáme
            'releaseInDays' => $game->daysUntilRelease(),
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
     * Aktivní bundly s hrou (R51) — s cenou v měně zobrazení od nejlevnějšího,
     * „Build your own“ bez ceny na konci (hra musí mít načtenou vazbu bundles).
     *
     * @return list<array{title: string, shop: string, url: string, price: array{amount: float, currency: string}|null, expiresAt: string|null}>
     */
    private function bundles(Game $game, string $currency): array
    {
        $converted = fn (GameBundle $bundle): ?Money => ($price = $bundle->price()) === null
            ? null
            : ($this->rates->convert($price, $currency) ?? $price);

        return array_values($game->bundles
            ->sortBy(fn (GameBundle $bundle): array => [$bundle->price() === null ? 1 : 0, $converted($bundle)->cents ?? 0])
            ->map(fn (GameBundle $bundle): array => [
                'title' => $bundle->title,
                'shop' => $bundle->shop,
                'url' => $bundle->url,
                'price' => $converted($bundle)?->toArray(),
                'expiresAt' => $bundle->expires_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Nejbližší budoucí odchod hry z katalogu PS Plus (R49) z cen PS Store
     * (hra musí mít načtenou vazbu currentPrices); null, když neodchází.
     */
    private function plusLeavesAt(Game $game): ?CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $leaving = $game->currentPrices
            ->map(fn (CurrentPrice $price): ?CarbonImmutable => $price->member_included ? $price->member_leaves_at : null)
            ->filter(fn (?CarbonImmutable $at): bool => $at !== null && $at->greaterThan($now))
            ->sort()
            ->first();

        return $leaving instanceof CarbonImmutable ? $leaving : null;
    }

    /**
     * Čeština po platformách (R47) — PC ze Steamu, PlayStation z připojených
     * produktů. Shodná na obou platformách (nebo známá jen u jedné) jde jako
     * jedna položka bez platformy; neznámá se vynechá.
     *
     * @return list<array{platform: string|null, support: string}>
     */
    private function czech(Game $game): array
    {
        $byPlatform = array_filter([
            Platform::Pc->value => $game->czech,
            Platform::PlayStation->value => CzechSupport::best(...$game->storeListings->map(fn (StoreListing $listing): ?CzechSupport => $listing->czech)->all()),
        ]);

        if (count(array_unique(array_map(fn (CzechSupport $support): string => $support->value, $byPlatform))) <= 1) {
            $support = reset($byPlatform);

            return $support === false ? [] : [['platform' => null, 'support' => $support->value]];
        }

        return array_map(
            fn (string $platform, CzechSupport $support): array => ['platform' => $platform, 'support' => $support->value],
            array_keys($byPlatform),
            array_values($byPlatform),
        );
    }

    /**
     * Hodnocení z ITAD; když ITAD Metascore nemá, doplní ho z Metacritic (R33).
     *
     * @return list<array{source: string, score: int, count: int|null, url: string|null}>
     */
    private function reviews(Game $game): array
    {
        $reviews = $game->reviews ?? [];
        if ($game->itadMetascore() === null && $game->metacritic_score !== null && $game->metacritic_slug !== null) {
            $reviews[] = [
                'source' => Game::METASCORE_SOURCE,
                'score' => $game->metacritic_score,
                'count' => $game->metacritic_reviews,
                'url' => MetacriticUrl::criticReviews($game->metacritic_slug),
            ];
        }

        return $reviews;
    }

    /**
     * Připojený produkt PS Store s nejvíc hodnoceními hráčů; null, když žádný
     * hodnocení nemá (hra musí mít načtenou vazbu storeListings).
     */
    private function mostRatedListing(Game $game): ?StoreListing
    {
        $listing = $game->storeListings
            ->filter(fn (StoreListing $listing): bool => $listing->star_rating !== null)
            ->sortByDesc('star_ratings_count')
            ->first();

        return $listing instanceof StoreListing ? $listing : null;
    }

    /**
     * Cena za hodinu hlavního příběhu (R44) — z nabídky v měně zobrazení;
     * null bez nabídky nebo bez délky z HowLongToBeat. `cents` je pro řazení.
     *
     * @param  array<string, mixed>|null  $offer  nabídka PriceBoard (effectiveCents)
     * @return array{price: array{amount: float, currency: string}, cents: int}|null
     */
    private function pricePerHour(Game $game, ?array $offer, string $currency): ?array
    {
        $minutes = $game->hltb_main_minutes;
        if ($offer === null || $minutes === null || $minutes <= 0) {
            return null;
        }

        $cents = (int) round((int) $offer['effectiveCents'] * self::MINUTES_PER_HOUR / $minutes);

        return ['price' => (new Money($cents, $currency))->toArray(), 'cents' => $cents];
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
