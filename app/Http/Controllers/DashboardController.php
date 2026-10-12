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

use App\Domain\Monitoring\SourceHealth;
use App\Domain\Monitoring\SourceMonitor;
use App\Domain\Watchlist\WatchlistView;
use App\Http\Requests\DashboardRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Úvodní stránka po přihlášení — osobní seznam sledovaných her (R11)
 * s pokročilými filtry v adrese (R21) a cenami v měně účtu (R14). Při výpadku
 * zdroje cen upozorní, že ceny nemusí být aktuální (R29).
 */
class DashboardController extends Controller
{
    /**
     * Vykreslí přehled.
     */
    public function __invoke(DashboardRequest $request, WatchlistView $view, SourceMonitor $monitor): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $watchlist = $user instanceof User
            ? $view->build($user, $filters, $user->displayCurrency())
            : ['games' => [], 'total' => 0, 'genres' => [], 'dailyDeals' => []];

        return Inertia::render('Dashboard', [
            'games' => $watchlist['games'],
            'total' => $watchlist['total'],
            'genres' => $watchlist['genres'],
            'dailyDeals' => $watchlist['dailyDeals'],
            'filters' => $filters->toArray(),
            'dashboardUrl' => route('dashboard', absolute: false),
            'searchUrl' => route('games.search', absolute: false),
            // Filtry načítají jen data přehledu — stav zdrojů se při nich nepočítá znovu
            'sourcesDown' => fn (): array => array_map(fn (SourceHealth $health): string => $health->source->value, $monitor->down()),
            'sourcesUrl' => route('sources.show', absolute: false),
        ]);
    }
}
