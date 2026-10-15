<?php

/**
 * Výsledky hledání hry pro stránku „Přidat hru“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Catalog\Actions\DescribeAvailability;
use App\Domain\Currency\ExchangeRates;
use App\Domain\Itad\ItadGame;
use App\Domain\PlayStation\Actions\FindPlayStationGamePages;
use App\Domain\Pricing\Money;
use App\Enums\Platform;
use App\Enums\SearchSort;
use App\Models\Game;
use App\Models\User;

/**
 * U výsledků vyznačí, co uživatel už sleduje, kde se hra prodává (R23, R24),
 * a vybere a seřadí je podle filtrů (R30). Obchody a stránky PS se zjišťují
 * pro všechny výsledky hledání — filtr pak jen vybírá, takže změna filtru
 * jde z cache a ITAD ani playstation.com znovu nevolá. Stránky PS, které cache
 * nezná, se zjišťují odloženě (R56), kromě filtru PlayStation, který je potřebuje.
 */
final class SearchResults
{
    public function __construct(
        private readonly DescribeAvailability $describeAvailability,
        private readonly FindPlayStationGamePages $findGamePages,
        private readonly GamePresenter $presenter,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Výsledky pro stránku podle filtrů.
     *
     * @param  list<ItadGame>  $games  výsledky hledání v ITAD
     * @return list<array<string, mixed>>
     */
    public function build(array $games, ?User $user, SearchFilters $filters): array
    {
        $itadIds = array_map(fn (ItadGame $game): string => $game->id, $games);
        $availability = $this->describeAvailability->handle($itadIds);
        // playstation.com odpovídá z hostingu pomalu (R56) — bez filtru PlayStation
        // jen z cache, zbytek dotáhne stránka odloženě (playStationPages())
        [$playstationPages, $pending] = $filters->platform === Platform::PlayStation
            ? [$this->findGamePages->handle($games), []]
            : $this->findGamePages->cached($games);
        $currency = $user?->displayCurrency() ?? (string) config('games.prices.default_currency');

        $inCatalog = Game::query()
            ->with('storeListings')
            ->whereIn('itad_id', $itadIds)
            ->get()
            ->keyBy('itad_id');

        $watchedIds = $user === null ? [] : $user->watchlistItems()
            ->whereIn('game_id', $inCatalog->pluck('id'))
            ->pluck('game_id')
            ->all();

        $entries = [];
        foreach ($games as $game) {
            if (! $filters->acceptsType($game->type)) {
                continue;
            }

            $catalogGame = $inCatalog->get($game->id);
            $gameAvailability = $availability === null ? null : ($availability[$game->id] ?? null);
            $result = $this->presenter->searchResult(
                $game,
                $catalogGame,
                $catalogGame !== null && in_array($catalogGame->id, $watchedIds, true),
                $gameAvailability,
                $playstationPages[$game->id] ?? null,
                $currency,
            );

            // Stránka na playstation.com se teprve zjišťuje — připojený PS Store ji nepotřebuje
            $result['playstationPending'] = in_array($game->id, $pending, true) && $catalogGame?->storeListings->isNotEmpty() !== true;

            if ($this->matchesPlatform($result, $gameAvailability, $filters->platform)) {
                $entries[] = ['result' => $result, 'price' => $this->price($gameAvailability, $currency)];
            }
        }

        if ($filters->sort === SearchSort::Price) {
            // usort je stabilní — hry se stejnou cenou zůstanou v pořadí z ITAD
            usort($entries, fn (array $a, array $b): int => ($a['price']->cents ?? PHP_INT_MAX) <=> ($b['price']->cents ?? PHP_INT_MAX));
        }

        return array_map(fn (array $entry): array => $entry['result'], $entries);
    }

    /**
     * Stránky na playstation.com pro výsledky, u kterých je build() nechal
     * nezjištěné (playstationPending) — odložený prop stránky hledání (R56).
     * Adresa stránky, nebo null, když hra stránku nemá nebo se to nepovedlo zjistit.
     *
     * @param  list<ItadGame>  $games  výsledky hledání v ITAD
     * @param  list<array<string, mixed>>  $results  výsledky z build()
     * @return array<string, string|null>
     */
    public function playStationPages(array $games, array $results): array
    {
        $pending = array_column(array_filter($results, fn (array $result): bool => $result['playstationPending'] === true), 'itadId');
        $pages = $this->findGamePages->handle(array_values(array_filter($games, fn (ItadGame $game): bool => in_array($game->id, $pending, true))));

        return array_combine($pending, array_map(fn (mixed $itadId): ?string => $pages[(string) $itadId] ?? null, $pending));
    }

    /**
     * Prodává se hra na platformě z filtru? PC = má obchod pro PC v ITAD
     * (při výpadku ITAD se to neví — hra zůstane), PlayStation = připojený
     * PS Store nebo stránka na playstation.com (R24).
     *
     * @param  array<string, mixed>  $result
     * @param  array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}|null  $availability
     */
    private function matchesPlatform(array $result, ?array $availability, ?Platform $platform): bool
    {
        return match ($platform) {
            null => true,
            Platform::Pc => $availability === null || $availability['shops'] !== [],
            Platform::PlayStation => $result['onPlayStation'] === true,
        };
    }

    /**
     * Nejnižší cena v obchodech pro PC v měně účtu — pro řazení; bez kurzu
     * null (částky v různých měnách se nesrovnávají, R14).
     *
     * @param  array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}|null  $availability
     */
    private function price(?array $availability, string $currency): ?Money
    {
        if ($availability === null || $availability['bestCents'] === null || $availability['bestCurrency'] === null) {
            return null;
        }

        return $this->rates->convert(new Money($availability['bestCents'], $availability['bestCurrency']), $currency);
    }
}
