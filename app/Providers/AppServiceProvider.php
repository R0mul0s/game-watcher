<?php

/**
 * Hlavní service provider aplikace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Currency\ExchangeRates;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registrace služeb.
     */
    public function register(): void
    {
        // Kurzovní lístek se načte jednou za požadavek, ne pro každou cenu (R14)
        $this->app->scoped(ExchangeRates::class);
    }

    /**
     * Nastavení při startu aplikace.
     */
    public function boot(): void
    {
        // Nejkratší zápis desetinných čísel v JSON (9.2, ne 9.1999999999999992…) —
        // PHP na hostingu má jiné výchozí serialize_precision než Docker (zkušenost
        // z projektu Počasí); ceny jdou do stránek jako desetinná čísla
        ini_set('serialize_precision', '-1');

        // Líné načítání vazeb ve vývoji a testech hlásit — na produkci jen zpomalí
        Model::preventLazyLoading(! $this->app->isProduction());

        // Hledání v ITAD na účet — klíč má limit 100 požadavků za 5 minut pro všechny
        RateLimiter::for('catalog-search', fn (Request $request): Limit => Limit::perMinute(
            (int) config('games.catalog.max_searches_per_minute'),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
