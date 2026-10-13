<?php

/**
 * Čeština ve hře ze Steamu a z PS Store (R47).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * games.czech ze Steamu, store_listings.czech z produktu PS Store. Údaje
 * ze Steamu se označí jako nestažené — cron je po dávkách stáhne znovu
 * i s jazyky; produkty PS Store doplní nejbližší obnova cen.
 */
return new class extends Migration
{
    /** Délka hodnoty výčtu CzechSupport (none / text / voice). */
    private const CZECH_LENGTH = 8;

    /**
     * Přidá sloupce a vynuluje stažení údajů ze Steamu.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('czech', self::CZECH_LENGTH)->nullable()->after('modes')
                ->comment('Čeština podle Steamu: none / text (rozhraní a titulky) / voice (dabing); NULL = neznámá');
        });

        Schema::table('store_listings', function (Blueprint $table): void {
            $table->string('czech', self::CZECH_LENGTH)->nullable()->after('star_ratings_count')
                ->comment('Čeština podle PS Store: none / text (rozhraní a titulky) / voice (dabing); NULL = neznámá');
        });

        DB::table('games')->whereNotNull('steam_details_at')->update(['steam_details_at' => null]);
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('czech');
        });

        Schema::table('store_listings', function (Blueprint $table): void {
            $table->dropColumn('czech');
        });
    }
};
