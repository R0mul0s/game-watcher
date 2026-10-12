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
use App\Http\Controllers\StoreListingController;
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
    // Přidání volá ITAD a s odkazem z hledání i PS Store (R24) — omezené jako propojení
    Route::post('/hry', [WatchlistController::class, 'store'])
        ->middleware('throttle:'.config('games.listings.max_attempts_per_minute').',1')
        ->name('watchlist.store');
    Route::get('/hry/{game}', [GameController::class, 'show'])->name('games.show');
    Route::put('/hry/{game}/sledovani', [WatchlistController::class, 'update'])->name('watchlist.update');
    Route::delete('/hry/{game}/sledovani', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    // Produkty PlayStation Store připojené ke hře odkazem (R20); propojení patří hře
    Route::post('/hry/{game}/obchody', [StoreListingController::class, 'store'])
        ->middleware('throttle:'.config('games.listings.max_attempts_per_minute').',1')
        ->name('listings.store');
    Route::delete('/hry/{game}/obchody/{storeListing}', [StoreListingController::class, 'destroy'])
        ->scopeBindings()
        ->name('listings.destroy');

    // Měna zobrazení cen (R14)
    Route::put('/nastaveni/mena', DisplayCurrencyController::class)->name('settings.currency');
});
