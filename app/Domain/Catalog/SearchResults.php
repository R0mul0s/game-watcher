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
 * jde z cache a ITAD ani playstation.com znovu nevolá.
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
        $playstationPages = $this->findGamePages->handle($games);
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
