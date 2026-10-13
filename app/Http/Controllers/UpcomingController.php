<?php

/**
 * Záložka Nadcházející na stránce Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Discover\Actions\WatchUpcomingRelease;
use App\Domain\Discover\UpcomingReleases;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Watchlist\Exceptions\GameNotFound;
use App\Enums\Platform;
use App\Http\Requests\UpcomingRequest;
use App\Models\UpcomingRelease;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nadcházející hry z IGDB (R46), které uživatel ještě nesleduje — seznam
 * z vlastní tabulky, sledování jedním klikem se spárováním s ITAD.
 */
class UpcomingController extends Controller
{
    /**
     * Stránka s nadcházejícími hrami a filtry.
     */
    public function index(UpcomingRequest $request, UpcomingReleases $upcomingReleases): Response
    {
        $filters = $request->filters();

        return Inertia::render('DiscoverUpcoming', [
            ...$upcomingReleases->build($this->user($request), $filters),
            'filters' => $filters->toArray(),
            'upcomingUrl' => route('discover.upcoming', absolute: false),
            'discoverUrl' => route('discover', absolute: false),
            'igdbUrl' => config('games.upcoming.site_url'),
        ]);
    }

    /**
     * Začne hru sledovat; hra jen na PS5 bez připojeného PS Store přejde
     * na detail, kde se připojuje (jako přidání z hledání, R24).
     */
    public function watch(Request $request, UpcomingRelease $upcomingRelease, WatchUpcomingRelease $watchUpcomingRelease): RedirectResponse
    {
        try {
            $game = $watchUpcomingRelease->handle($this->user($request), $upcomingRelease);
        } catch (GameNotFound) {
            return Inertia::flash('error', __('app.upcoming.not_in_catalog', ['title' => $upcomingRelease->title]))->back();
        } catch (ItadUnavailable) {
            return Inertia::flash('error', __('app.catalog.unavailable'))->back();
        }

        $platform = $this->user($request)->watchlistItemFor($game)?->platform;
        if ($platform === Platform::PlayStation && $game->storeListings()->doesntExist()) {
            Inertia::flash('success', __('app.watchlist.added_connect_playstation', ['title' => $game->title]));

            return redirect()->route('games.show', $game);
        }

        return Inertia::flash('success', __('app.watchlist.added', ['title' => $game->title]))->back();
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
