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
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\DisplayCurrencyController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\HltbController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MetacriticController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceStatusController;
use App\Http\Controllers\StoreListingController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// Aplikace na ploše a upozornění na telefon (R13) — bez přihlášení: prohlížeč
// je stahuje i mimo přihlášenou stránku (aktualizace service workeru)
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('/sw.js', ServiceWorkerController::class)->name('service-worker');
// Stránka bez připojení — service worker si ji uloží předem (R37), bez dat účtu
Route::get('/offline', [ServiceWorkerController::class, 'offline'])->name('offline');

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

    // Slevy her podle štítků sledovaných her (R43) — každá kombinace filtrů je
    // dotaz na ITAD (v cache), omezené jako hledání
    Route::get('/objevit', DiscoverController::class)
        ->middleware('throttle:catalog-search')
        ->name('discover');
    Route::put('/hry/{game}/sledovani', [WatchlistController::class, 'update'])->name('watchlist.update');
    // Cílová cena pro upozornění (etapa 6, R26)
    Route::put('/hry/{game}/cil', [WatchlistController::class, 'target'])->name('watchlist.target');
    Route::delete('/hry/{game}/sledovani', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    // Produkty PlayStation Store připojené ke hře odkazem (R20); propojení patří hře
    Route::post('/hry/{game}/obchody', [StoreListingController::class, 'store'])
        ->middleware('throttle:'.config('games.listings.max_attempts_per_minute').',1')
        ->name('listings.store');
    Route::delete('/hry/{game}/obchody/{storeListing}', [StoreListingController::class, 'destroy'])
        ->scopeBindings()
        ->name('listings.destroy');

    // Stránka hry na Metacritic odkazem (R33) — volá Metacritic, omezené jako propojení
    Route::put('/hry/{game}/metacritic', [MetacriticController::class, 'update'])
        ->middleware('throttle:'.config('games.listings.max_attempts_per_minute').',1')
        ->name('metacritic.update');

    // Hra na HowLongToBeat odkazem (R44) — volá HLTB, omezené jako propojení
    Route::put('/hry/{game}/delka-hrani', [HltbController::class, 'update'])
        ->middleware('throttle:'.config('games.listings.max_attempts_per_minute').',1')
        ->name('hltb.update');

    // Měna zobrazení cen (R14)
    Route::put('/nastaveni/mena', DisplayCurrencyController::class)->name('settings.currency');

    // Stav zdrojů cen — kdy se který naposledy povedl (R29)
    Route::get('/stav-zdroju', SourceStatusController::class)->name('sources.show');

    // Nastavení upozornění a zařízení s Web Push (etapa 6, R13, R26)
    Route::get('/nastaveni', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('/nastaveni', [SettingsController::class, 'update'])->name('settings.update');
    Route::middleware('throttle:'.config('games.push.max_attempts_per_minute').',1')->group(function (): void {
        Route::post('/nastaveni/zarizeni', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/nastaveni/zarizeni', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('/nastaveni/zarizeni/test', [PushSubscriptionController::class, 'test'])->name('push.test');
    });
});
