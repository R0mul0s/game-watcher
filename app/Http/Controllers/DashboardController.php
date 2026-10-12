<?php

/**
 * Přehled sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Watchlist\WatchlistView;
use App\Http\Requests\DashboardRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Úvodní stránka po přihlášení — osobní seznam sledovaných her (R11)
 * s pokročilými filtry v adrese (R21) a cenami v měně účtu (R14).
 */
class DashboardController extends Controller
{
    /**
     * Vykreslí přehled.
     */
    public function __invoke(DashboardRequest $request, WatchlistView $view): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $watchlist = $user instanceof User
            ? $view->build($user, $filters, $user->displayCurrency())
            : ['games' => [], 'total' => 0, 'genres' => []];

        return Inertia::render('Dashboard', [
            'games' => $watchlist['games'],
            'total' => $watchlist['total'],
            'genres' => $watchlist['genres'],
            'filters' => $filters->toArray(),
            'dashboardUrl' => route('dashboard', absolute: false),
            'searchUrl' => route('games.search', absolute: false),
        ]);
    }
}
