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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registrace služeb — zatím žádné.
     */
    public function register(): void
    {
        //
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
    }
}
