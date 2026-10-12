<?php

/**
 * Webové routy aplikace.
 *
 * Kromě přihlášení je všechno za přihlášením (R3). Adresy jsou česky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayCurrencyController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/prihlaseni', [LoginController::class, 'create'])->name('login');
    Route::post('/prihlaseni', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/odhlaseni', [LoginController::class, 'destroy'])->name('logout');

    // Katalog a seznam sledovaných her (etapa 2). /hry/pridat musí být před
    // /hry/{game}, jinak by se „pridat“ hledalo jako slug hry.
    Route::get('/hry/pridat', GameSearchController::class)
        ->middleware('throttle:catalog-search')
        ->name('games.search');
    Route::post('/hry', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/hry/{game}', [GameController::class, 'show'])->name('games.show');
    Route::delete('/hry/{game}/sledovani', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    // Měna zobrazení cen (R14)
    Route::put('/nastaveni/mena', DisplayCurrencyController::class)->name('settings.currency');
});
