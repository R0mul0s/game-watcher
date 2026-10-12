<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Cron URL běží mimo skupinu `web` — bez session a CSRF (R2)
        then: function (): void {
            Route::group([], base_path('routes/cron.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sdílený hosting ukončuje HTTPS na své proxy a do PHP posílá HTTP s X-Forwarded-*.
        // Bez důvěry k proxy by Laravel generoval http:// odkazy na https:// stránce.
        // Adresa proxy není pevná (interní síť hostingu), proto všechny.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Nepřihlášený na přihlášení, přihlášený z přihlášení na přehled (R3)
        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(fn (): string => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Výchozí chování stačí — JSON dostane, kdo ho žádá (Accept); API routy nejsou.
    })->create();
