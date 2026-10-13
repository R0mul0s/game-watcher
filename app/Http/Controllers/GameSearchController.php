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

use App\Domain\Catalog\Actions\SearchByPlayStationLink;
use App\Domain\Catalog\Actions\SearchGames;
use App\Domain\Catalog\SearchResults;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Http\Requests\GameSearchRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hledá v IsThereAnyDeal (etapa 2) podle názvu, Steamu, odkazu z PS Store
 * (R24) nebo z ITAD (R30); výsledky s obchody a filtry připraví SearchResults.
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
        SearchResults $searchResults,
    ): Response {
        $query = $request->gameQuery();
        $playstationUrl = $request->playStationUrl();
        $filters = $request->searchFilters();
        $user = $request->user();
        $results = [];
        $found = 0;
        $unavailable = false;
        $playstation = null;
        $playstationError = null;

        try {
            $games = [];
            if ($playstationUrl !== null) {
                // Odkaz z PS Store nebo playstation.com — hledá se podle názvu produktu (R24)
                $byLink = $searchByPlayStationLink->handle($playstationUrl);
                $playstation = $byLink['product'];
                $games = $byLink['games'];
            } elseif ($query !== null) {
                $games = $searchGames->handle($query, $filters->limit());
            }

            $found = count($games);
            $results = $searchResults->build($games, $user instanceof User ? $user : null, $filters);
        } catch (ItadUnavailable) {
            // Podrobnosti zalogoval klient; uživatel jen uvidí, že hledání teď nejde
            $unavailable = true;
        } catch (PlayStationProductNotFound) {
            $playstationError = __('app.listings.not_playstation');
        } catch (PlayStationUnavailable) {
            $playstationError = __('app.listings.unavailable');
        }

        return Inertia::render('Games/Search', [
            // I odkaz sdílený z jiné aplikace (R37) — pole hledání ho ukáže
            'query' => $request->queryText(),
            'searched' => $query !== null,
            'results' => $results,
            // Kolik našel ITAD před filtry — „žádná hra filtrům“ vs. „nic jsme nenašli“
            'found' => $found,
            // Plný počet výsledků naznačuje, že ITAD má víc (R30)
            'canShowMore' => ! $filters->more && $playstationUrl === null && $found >= $filters->limit(),
            'filters' => $filters->toArray(),
            'unavailable' => $unavailable,
            'playstation' => $playstation,
            'playstationError' => $playstationError,
            'searchUrl' => route('games.search', absolute: false),
            'watchUrl' => route('watchlist.store', absolute: false),
        ]);
    }
}
