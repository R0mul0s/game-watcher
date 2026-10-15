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
use App\Domain\Monitoring\SlowRequestLog;
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
        SlowRequestLog $slowLog,
    ): Response {
        $query = $request->gameQuery();
        $playstationUrl = $request->playStationUrl();
        $filters = $request->searchFilters();
        $user = $request->user();
        $games = [];
        $results = [];
        $unavailable = false;
        $playstation = null;
        $playstationError = null;

        try {
            // Pomalé hledání zapíše časy volání zdrojů do logu (R56)
            [$playstation, $games, $results] = $slowLog->measure(sprintf('hledání „%s“', (string) $request->queryText()), function () use (
                $playstationUrl, $query, $searchByPlayStationLink, $searchGames, $searchResults, $filters, $user,
            ): array {
                $product = null;
                $games = [];
                if ($playstationUrl !== null) {
                    // Odkaz z PS Store nebo playstation.com — hledá se podle názvu produktu (R24)
                    $byLink = $searchByPlayStationLink->handle($playstationUrl);
                    $product = $byLink['product'];
                    $games = $byLink['games'];
                } elseif ($query !== null) {
                    $games = $searchGames->handle($query, $filters->limit());
                }

                return [$product, $games, $searchResults->build($games, $user instanceof User ? $user : null, $filters)];
            });
        } catch (ItadUnavailable) {
            // Podrobnosti zalogoval klient; uživatel jen uvidí, že hledání teď nejde
            $unavailable = true;
        } catch (PlayStationProductNotFound) {
            $playstationError = __('app.listings.not_playstation');
        } catch (PlayStationUnavailable) {
            $playstationError = __('app.listings.unavailable');
        }

        $found = count($games);
        $pending = array_filter($results, fn (array $result): bool => $result['playstationPending'] === true) !== [];

        return Inertia::render('Games/Search', [
            // I odkaz sdílený z jiné aplikace (R37) — pole hledání ho ukáže
            'query' => $request->queryText(),
            'searched' => $query !== null,
            'results' => $results,
            // Stránky na playstation.com, které cache nezná — dotáhnou se po
            // zobrazení výsledků (z hostingu odpovídají i 4 s, R56)
            'playstationPages' => $pending ? Inertia::defer(fn (): array => $slowLog->measure(
                sprintf('ověření PlayStationu pro „%s“', (string) $request->queryText()),
                fn (): array => $searchResults->playStationPages($games, $results),
            )) : null,
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
