<?php

/**
 * Přehled sledovaných her s filtry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Watchlist;

use App\Domain\Catalog\GamePresenter;
use App\Enums\Platform;
use App\Enums\WatchlistSort;
use App\Models\Game;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Collection;

/**
 * Filtruje a řadí server (R21) — sledovaných her je desítky, ne tisíce, takže
 * se karty spočítají všechny a pak se vybírá; „nejlevněji“ i cena pro filtr
 * jsou z PriceBoard ve stejné měně, jakou uživatel vidí. Žánr a počet hráčů
 * (R25) jsou údaje hry ze Steamu a PS Store — hra bez údajů s takovým filtrem
 * vypadne.
 */
final class WatchlistView
{
    public function __construct(
        private readonly GamePresenter $presenter,
        private readonly DailyDeals $dailyDeals,
    ) {}

    /**
     * Karty her podle filtrů, počet všech sledovaných a nabídka žánrů pro filtr.
     *
     * @return array{games: list<array<string, mixed>>, total: int, genres: list<array{name: string, count: int}>, dailyDeals: list<array<string, mixed>>}
     */
    public function build(User $user, WatchlistFilters $filters, string $currency): array
    {
        $games = $user->watchedGames()->with(['currentPrices', 'priceLows', 'storeListings'])->get();
        $priceFilter = $filters->priceFilter();
        $items = $user->watchlistItemsByGame();
        $watchPlatforms = $items->map(fn (WatchlistItem $item): ?Platform => $item->platform);

        // Hra hlídaná jen na jedné platformě počítá jen s jejími cenami (R24)
        $cards = $games
            ->filter(fn (Game $game): bool => $this->matchesGame($game, $filters, $watchPlatforms->get($game->id)))
            ->map(fn (Game $game): array => $this->presenter->card(
                $game,
                $currency,
                $priceFilter->restrictedTo($watchPlatforms->get($game->id)),
                $items->get($game->id),
            ))
            ->filter(fn (array $card): bool => $this->matchesPrice($card, $filters));

        return [
            'games' => array_values($this->sorted($cards, $filters->sort)->all()),
            'total' => $games->count(),
            'genres' => $this->genres($games),
            // Slevy dne nezávisí na filtrech — sekce nad nimi (R26)
            'dailyDeals' => $this->dailyDeals->handle($games, $watchPlatforms, $currency),
        ];
    }

    /**
     * Filtry, které nezávisí na ceně — název, druh, žánr a počet hráčů; hra
     * hlídaná jen na jiné platformě, než chce filtr, vypadne (R24).
     */
    private function matchesGame(Game $game, WatchlistFilters $filters, ?Platform $watchPlatform): bool
    {
        return ($filters->query === '' || mb_stripos($game->title, $filters->query) !== false)
            && ($filters->type === null || $game->type === $filters->type)
            && ($filters->platform === null || $watchPlatform === null || $watchPlatform === $filters->platform)
            && ($filters->genres === null || array_intersect($filters->genres, $game->genres ?? []) !== [])
            && ($filters->players === null || $filters->players->matches($game->playModes()));
    }

    /**
     * Filtry ceny nad nejlevnější nabídkou karty (obchody s klíči se do ní
     * nepočítají — mají vlastní řádek). Hra bez ceny projde jen bez cenových filtrů.
     *
     * @param  array<string, mixed>  $card
     */
    private function matchesPrice(array $card, WatchlistFilters $filters): bool
    {
        $best = $card['prices']['best'];
        if (! $filters->requiresPrice()) {
            return true;
        }

        if ($best === null) {
            return false;
        }

        $discounted = $best['cut'] > 0 || ($filters->withPlus && $best['member'] !== null);

        return (! $filters->dealsOnly || $discounted)
            && ($filters->maxPriceCents === null || $best['effectiveCents'] <= $filters->maxPriceCents)
            && ($filters->minCut === null || $best['cut'] >= $filters->minCut);
    }

    /**
     * Karty v pořadí podle volby; hry bez ceny nebo data vydání na konci.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function sorted(Collection $cards, WatchlistSort $sort): Collection
    {
        return match ($sort) {
            WatchlistSort::Added => $cards,
            WatchlistSort::Title => $cards->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE),
            WatchlistSort::Price => $cards->sortBy(fn (array $card): int => $card['prices']['best']['effectiveCents'] ?? PHP_INT_MAX),
            WatchlistSort::Cut => $cards->sortByDesc(fn (array $card): int => $card['prices']['best']['cut'] ?? -1),
            WatchlistSort::Release => $cards->sortByDesc(fn (array $card): string => $card['releaseDate'] ?? ''),
        };
    }

    /**
     * Žánry sledovaných her s počtem her — od nejčastějšího.
     *
     * @param  Collection<int, Game>  $games
     * @return list<array{name: string, count: int}>
     */
    private function genres(Collection $games): array
    {
        $genres = [];
        foreach ($games as $game) {
            foreach ($game->genres ?? [] as $genre) {
                $genres[$genre] ??= ['name' => $genre, 'count' => 0];
                $genres[$genre]['count']++;
            }
        }

        return array_values(collect($genres)->sortBy([['count', 'desc'], ['name', 'asc']])->all());
    }
}
