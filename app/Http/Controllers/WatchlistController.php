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
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Domain\Watchlist\Actions\UnwatchGame;
use App\Domain\Watchlist\Actions\WatchGame;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Enums\Platform;
use App\Http\Requests\WatchGameRequest;
use App\Http\Requests\WatchPlatformRequest;
use App\Http\Requests\WatchTargetRequest;
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
     * S odkazem z hledání podle PS Store produkt rovnou připojí; hra hlídaná jen
     * na PlayStationu bez připojeného PS Store přejde na detail, kde se
     * připojuje (R24) — bez něj by neměla cenu.
     */
    public function store(WatchGameRequest $request, WatchGame $watchGame, ConnectPlayStationProduct $connect): RedirectResponse
    {
        $platform = $request->platform();

        try {
            $game = $watchGame->handle($this->user($request), $request->string('itad_id')->toString(), $platform);
        } catch (GameNotFound) {
            return Inertia::flash('error', __('app.catalog.game_not_found'))->back();
        } catch (ItadUnavailable) {
            return Inertia::flash('error', __('app.catalog.unavailable'))->back();
        }

        $listingUrl = $request->listingUrl();
        if ($listingUrl !== null) {
            try {
                $listing = $connect->handle($game, $listingUrl);
            } catch (PlayStationProductNotFound) {
                return $this->toDetail($game, 'error', __('app.watchlist.added_listing_failed', ['title' => $game->title]));
            } catch (PlayStationUnavailable) {
                return $this->toDetail($game, 'error', __('app.watchlist.added_listing_unavailable', ['title' => $game->title]));
            }

            return Inertia::flash('success', __('app.watchlist.added_with_listing', ['title' => $game->title, 'name' => $listing->name]))->back();
        }

        if ($platform === Platform::PlayStation && $game->storeListings()->doesntExist()) {
            return $this->toDetail($game, 'success', __('app.watchlist.added_connect_playstation', ['title' => $game->title]));
        }

        return Inertia::flash('success', __('app.watchlist.added', ['title' => $game->title]))->back();
    }

    /**
     * Změní platformu, na které uživatel hru hlídá (R24); hru musí sledovat.
     */
    public function update(WatchPlatformRequest $request, Game $game): RedirectResponse
    {
        $item = $this->user($request)->watchlistItemFor($game) ?? abort(404);
        $item->update(['platform' => $request->platform()]);

        return Inertia::flash('success', __('app.watchlist.platform_changed'))->back();
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
     * Uloží cílovou cenu v měně zobrazení účtu (R26); stav posledního
     * upozornění smaže — nový cíl se vyhodnotí od začátku.
     */
    public function target(WatchTargetRequest $request, Game $game): RedirectResponse
    {
        $user = $this->user($request);
        $item = $user->watchlistItemFor($game) ?? abort(404);
        $target = $request->target($user->displayCurrency());

        $item->update([
            'target_amount' => $target?->toDecimal(),
            'target_currency' => $target?->currency,
            'notified_amount' => null,
            'notified_currency' => null,
        ]);

        return Inertia::flash('success', __($target === null ? 'app.alerts.target_cleared' : 'app.alerts.target_saved'))->back();
    }

    /**
     * Přesměrování na detail hry s hláškou.
     */
    private function toDetail(Game $game, string $type, string $message): RedirectResponse
    {
        Inertia::flash($type, $message);

        return redirect()->route('games.show', $game);
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
