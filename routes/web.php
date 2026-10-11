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
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/prihlaseni', [LoginController::class, 'create'])->name('login');
    Route::post('/prihlaseni', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/odhlaseni', [LoginController::class, 'destroy'])->name('logout');
});
