<?php

/**
 * Detail hry z katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\GamePresenter;
use App\Domain\Pricing\PriceChart;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Katalog je společný (R11) — detail vidí každý přihlášený, i když hru nesleduje.
 * Ceny v měně účtu (R14).
 */
class GameController extends Controller
{
    /**
     * Stránka detailu hry.
     */
    public function show(Request $request, Game $game, GamePresenter $presenter, PriceChart $chart): Response
    {
        $user = $request->user();
        $game->load(['currentPrices', 'priceLows', 'storeListings']);
        $item = $user instanceof User ? $user->watchlistItemFor($game) : null;
        $currency = $user instanceof User ? $user->displayCurrency() : (string) config('games.prices.default_currency');

        return Inertia::render('Games/Show', [
            'game' => $presenter->detail($game, $item, $currency),
            'watchUrl' => route('watchlist.store', absolute: false),
            // Graf vývoje ceny až po zobrazení stránky — historie ITAD může chvíli trvat (R28)
            'chart' => Inertia::defer(fn (): array => $chart->build($game, $currency, $item)),
        ]);
    }
}
