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

use App\Domain\Catalog\Actions\SearchGames;
use App\Domain\Catalog\GamePresenter;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadGame;
use App\Http\Requests\GameSearchRequest;
use App\Models\Game;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hledá v IsThereAnyDeal (etapa 2) a u výsledků vyznačí, co uživatel už sleduje.
 */
class GameSearchController extends Controller
{
    /**
     * Stránka s formulářem a výsledky; výpadek ITAD ukáže jako hlášku.
     */
    public function __invoke(GameSearchRequest $request, SearchGames $searchGames, GamePresenter $presenter): Response
    {
        $query = $request->gameQuery();
        $results = [];
        $unavailable = false;

        if ($query !== null) {
            try {
                $results = $this->present($searchGames->handle($query), $request->user(), $presenter);
            } catch (ItadUnavailable) {
                // Podrobnosti zalogoval klient; uživatel jen uvidí, že hledání teď nejde
                $unavailable = true;
            }
        }

        return Inertia::render('Games/Search', [
            'query' => $request->string('q')->toString(),
            'searched' => $query !== null,
            'results' => $results,
            'unavailable' => $unavailable,
            'searchUrl' => route('games.search', absolute: false),
            'watchUrl' => route('watchlist.store', absolute: false),
        ]);
    }

    /**
     * Výsledky pro stránku — které hry už katalog má a které uživatel sleduje.
     *
     * @param  list<ItadGame>  $games
     * @return list<array<string, mixed>>
     */
    private function present(array $games, ?User $user, GamePresenter $presenter): array
    {
        $inCatalog = Game::query()
            ->whereIn('itad_id', array_map(fn (ItadGame $game): string => $game->id, $games))
            ->get()
            ->keyBy('itad_id');

        $watchedIds = $user === null ? [] : $user->watchlistItems()
            ->whereIn('game_id', $inCatalog->pluck('id'))
            ->pluck('game_id')
            ->all();

        return array_map(function (ItadGame $game) use ($inCatalog, $watchedIds, $presenter): array {
            $catalogGame = $inCatalog->get($game->id);

            return $presenter->searchResult(
                $game,
                $catalogGame,
                $catalogGame !== null && in_array($catalogGame->id, $watchedIds, true),
            );
        }, $games);
    }
}
