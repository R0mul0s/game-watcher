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

use App\Domain\Catalog\GamePresenter;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Úvodní stránka po přihlášení — osobní seznam sledovaných her (R11),
 * naposledy přidané první. Ceny přibudou s etapou 3.
 */
class DashboardController extends Controller
{
    /**
     * Vykreslí přehled.
     */
    public function __invoke(Request $request, GamePresenter $presenter): Response
    {
        $user = $request->user();
        $games = $user instanceof User ? $user->watchedGames()->get() : collect();

        return Inertia::render('Dashboard', [
            'games' => $games->map(fn (Game $game): array => $presenter->card($game))->values()->all(),
            'searchUrl' => route('games.search', absolute: false),
        ]);
    }
}
