<?php

/**
 * Routy pro cron hostingu a externí monitoring.
 *
 * Načítají se bez skupiny `web` (bez session, cookies a CSRF) — cron z WebAdminu
 * i monitoring jen zavolají URL (R2, R29).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Http\Controllers\CronController;
use App\Http\Controllers\HealthSourcesController;
use Illuminate\Support\Facades\Route;

Route::get('/cron/ceny', CronController::class)
    ->middleware('throttle:'.config('games.cron.max_attempts_per_minute').',1')
    ->name('cron.run');

Route::get(config('games.monitoring.path'), HealthSourcesController::class)
    ->middleware('throttle:'.config('games.monitoring.max_attempts_per_minute').',1')
    ->name('health.sources');
