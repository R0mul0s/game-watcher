<?php

/**
 * Routa pro cron hostingu.
 *
 * Načítá se bez skupiny `web` (bez session, cookies a CSRF) — cron z WebAdminu
 * jen zavolá URL s tokenem (R2).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

Route::get('/cron/ceny', CronController::class)
    ->middleware('throttle:'.config('games.cron.max_attempts_per_minute').',1')
    ->name('cron.run');
