<?php

/**
 * Hledání hry k přidání na seznam sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\DescribeAvailability;
use App\Domain\Catalog\Actions\SearchByPlayStationLink;
use App\Domain\Catalog\Actions\SearchGames;
use App\Domain\Catalog\GamePresenter;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadGame;
use App\Domain\PlayStation\Actions\FindPlayStationGamePages;
use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Http\Requests\GameSearchRequest;
use App\Models\Game;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hledá v IsThereAnyDeal (etapa 2) podle názvu, Steamu nebo odkazu z PS Store
 * (R24), u výsledků vyznačí, co uživatel už sleduje, a kde se hra prodává (R23).
 */
class GameSearchController extends Controller
{
    /**
     * Stránka s formulářem a výsledky; výpadek ITAD ukáže jako hlášku.
     */
    public function __invoke(
        GameSearchRequest $request,
        SearchGames $searchGames,
        SearchByPlayStationLink $searchByPlayStationLink,
        DescribeAvailability $describeAvailability,
        FindPlayStationGamePages $findGamePages,
        GamePresenter $presenter,
    ): Response {
        $query = $request->gameQuery();
        $playstationUrl = $request->playStationUrl();
        $results = [];
        $unavailable = false;
        $playstation = null;
        $playstationError = null;

        try {
            $games = [];
            if ($playstationUrl !== null) {
                // Odkaz z PS Store nebo playstation.com — hledá se podle názvu produktu (R24)
                $found = $searchByPlayStationLink->handle($playstationUrl);
                $playstation = $found['product'];
                $games = $found['games'];
            } elseif ($query !== null) {
                $games = $searchGames->handle($query);
            }

            $results = $this->present($games, $request->user(), $describeAvailability, $findGamePages, $presenter);
        } catch (ItadUnavailable) {
            // Podrobnosti zalogoval klient; uživatel jen uvidí, že hledání teď nejde
            $unavailable = true;
        } catch (PlayStationProductNotFound) {
            $playstationError = __('app.listings.not_playstation');
        } catch (PlayStationUnavailable) {
            $playstationError = __('app.listings.unavailable');
        }

        return Inertia::render('Games/Search', [
            'query' => $request->string('q')->toString(),
            'searched' => $query !== null,
            'results' => $results,
            'unavailable' => $unavailable,
            'playstation' => $playstation,
            'playstationError' => $playstationError,
            'searchUrl' => route('games.search', absolute: false),
            'watchUrl' => route('watchlist.store', absolute: false),
        ]);
    }

    /**
     * Výsledky pro stránku — které hry už katalog má, které uživatel sleduje
     * a kde se prodávají (obchody pro PC z ITAD, PlayStation podle stránky hry).
     *
     * @param  list<ItadGame>  $games
     * @return list<array<string, mixed>>
     */
    private function present(
        array $games,
        ?User $user,
        DescribeAvailability $describeAvailability,
        FindPlayStationGamePages $findGamePages,
        GamePresenter $presenter,
    ): array {
        $itadIds = array_map(fn (ItadGame $game): string => $game->id, $games);
        $availability = $describeAvailability->handle($itadIds);
        $playstationPages = $findGamePages->handle($games);
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

        return array_map(function (ItadGame $game) use ($inCatalog, $watchedIds, $availability, $playstationPages, $currency, $presenter): array {
            $catalogGame = $inCatalog->get($game->id);

            return $presenter->searchResult(
                $game,
                $catalogGame,
                $catalogGame !== null && in_array($catalogGame->id, $watchedIds, true),
                $availability === null ? null : ($availability[$game->id] ?? null),
                $playstationPages[$game->id] ?? null,
                $currency,
            );
        }, $games);
    }
}
