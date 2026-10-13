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
use Illuminate\Support\Str;

/**
 * Filtruje a řadí server (R21) — sledovaných her je desítky, ne tisíce, takže
 * se karty spočítají všechny a pak se vybírá; „nejlevněji“ i cena pro filtr
 * jsou z PriceBoard ve stejné měně, jakou uživatel vidí, podle zvoleného zdroje
 * ceny (oficiální, včetně klíčů, jen klíče — R30). Žánr a počet hráčů
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
     * Filtry, které nezávisí na ceně — hledání, druh, vydání (R41), žánr, počet hráčů a čeština (R47); hra
     * hlídaná jen na jiné platformě, než chce filtr, vypadne (R24).
     */
    private function matchesGame(Game $game, WatchlistFilters $filters, ?Platform $watchPlatform): bool
    {
        return ($filters->query === '' || $this->matchesQuery($game, $filters->query))
            && ($filters->type === null || $game->type === $filters->type)
            && ($filters->release === null || $filters->release->matches($game))
            && ($filters->platform === null || $watchPlatform === null || $watchPlatform === $filters->platform)
            && ($filters->genres === null || array_intersect($filters->genres, $game->genres ?? []) !== [])
            && ($filters->players === null || $filters->players->matches($game->playModes()))
            && ($filters->czech === null || $filters->czech->matches($game->czechSupport()));
    }

    /**
     * Hledání v názvu, vývojářích, vydavatelích, štítcích a žánrech (R30) —
     * každé slovo dotazu musí někde být; bez ohledu na velikost písmen
     * a diakritiku („akcni“ najde „Akční“).
     */
    private function matchesQuery(Game $game, string $query): bool
    {
        $haystack = self::normalized(implode(' ', [
            $game->title,
            ...$game->developers ?? [],
            ...$game->publishers ?? [],
            ...$game->tags ?? [],
            ...$game->genres ?? [],
        ]));

        foreach (preg_split('/\s+/u', self::normalized($query), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Text pro hledání — malá písmena bez diakritiky.
     */
    private static function normalized(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * Cenové filtry (R21, R30). „Cena do“ a platforma se řídí cenou podle volby
     * „Cena“ (`ranked`), sleva vždy oficiální nabídkou — obchody s klíči slevu
     * neuvádějí. Hra bez ceny projde jen bez cenových filtrů.
     *
     * @param  array<string, mixed>  $card
     */
    private function matchesPrice(array $card, WatchlistFilters $filters): bool
    {
        if (! $filters->requiresPrice()) {
            return true;
        }

        $best = $card['prices']['best'];
        $ranked = $card['ranked'];
        if ($ranked === null && $best === null) {
            return false;
        }

        if ($filters->maxPriceCents !== null && ($ranked === null || $ranked['effectiveCents'] > $filters->maxPriceCents)) {
            return false;
        }

        if (! $filters->dealsOnly && $filters->minCut === null) {
            return true;
        }

        if ($best === null) {
            return false;
        }

        $discounted = $best['cut'] > 0 || ($filters->withPlus && $best['member'] !== null);

        return (! $filters->dealsOnly || $discounted)
            && ($filters->minCut === null || $best['cut'] >= $filters->minCut);
    }

    /**
     * Karty v pořadí podle volby; hry bez ceny, data vydání nebo hodnocení na konci.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function sorted(Collection $cards, WatchlistSort $sort): Collection
    {
        return match ($sort) {
            WatchlistSort::Added => $cards,
            WatchlistSort::Title => $cards->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE),
            WatchlistSort::Price => $cards->sortBy(fn (array $card): int => $card['ranked']['effectiveCents'] ?? PHP_INT_MAX),
            WatchlistSort::Cut => $cards->sortByDesc(fn (array $card): int => $card['prices']['best']['cut'] ?? -1),
            WatchlistSort::Release => $cards->sortByDesc(fn (array $card): string => $card['releaseDate'] ?? ''),
            WatchlistSort::Score => $cards->sortByDesc(fn (array $card): int => $card['metascore']['score'] ?? -1),
            WatchlistSort::PerHour => $cards->sortBy(fn (array $card): int => $card['pricePerHour']['cents'] ?? PHP_INT_MAX),
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
