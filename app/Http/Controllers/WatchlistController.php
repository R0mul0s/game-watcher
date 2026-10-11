<?php

/**
 * Přidání a odebrání hry ze seznamu sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Watchlist\Actions\UnwatchGame;
use App\Domain\Watchlist\Actions\WatchGame;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Http\Requests\WatchGameRequest;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Obě akce vrací uživatele tam, odkud přišel (hledání, detail), s jednorázovou
 * hláškou (Inertia flash — neukládá se do historie prohlížeče), takže
 * z výsledků hledání jde přidat víc her za sebou.
 */
class WatchlistController extends Controller
{
    /**
     * Přidá hru podle ITAD ID; hru, kterou katalog nemá, nejdřív převezme z ITAD.
     */
    public function store(WatchGameRequest $request, WatchGame $watchGame): RedirectResponse
    {
        try {
            $game = $watchGame->handle($this->user($request), $request->string('itad_id')->toString());
        } catch (GameNotFound) {
            return Inertia::flash('error', __('app.catalog.game_not_found'))->back();
        } catch (ItadUnavailable) {
            return Inertia::flash('error', __('app.catalog.unavailable'))->back();
        }

        return Inertia::flash('success', __('app.watchlist.added', ['title' => $game->title]))->back();
    }

    /**
     * Odebere hru ze seznamu; v katalogu zůstane.
     */
    public function destroy(Request $request, Game $game, UnwatchGame $unwatchGame): RedirectResponse
    {
        $unwatchGame->handle($this->user($request), $game);

        return Inertia::flash('success', __('app.watchlist.removed', ['title' => $game->title]))->back();
    }

    /**
     * Přihlášený uživatel — routy jsou za middlewarem `auth`.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
