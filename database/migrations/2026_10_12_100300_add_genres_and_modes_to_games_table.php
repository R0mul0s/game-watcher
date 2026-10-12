<?php

/**
 * Žánry a režimy hry (Steam) a počet hráčů produktu PS Store (R25).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * games — žánry a režimy ze Steamu (appdetails, česky) a kdy se stáhly;
 * store_listings — počet hráčů z PS Store (u jedné konzole a online).
 */
return new class extends Migration
{
    /**
     * Přidá sloupce.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->json('genres')->nullable()->after('tags')
                ->comment('Žánry ze Steamu (česky)');
            $table->json('modes')->nullable()->after('genres')
                ->comment('Režimy ze Steamu (enum GameMode: single, multi, coop, online_coop, local_coop, local_multi)');
            $table->timestamp('steam_details_at')->nullable()->after('info_refreshed_at')
                ->comment('Kdy se stáhly žánry a režimy ze Steamu');
        });

        Schema::table('store_listings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('local_players')->nullable()->after('platforms')
                ->comment('Nejvyšší počet hráčů u jedné konzole (PS Store)');
            $table->unsignedTinyInteger('online_players')->nullable()->after('local_players')
                ->comment('Nejvyšší počet hráčů online (PS Store)');
        });
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('store_listings', function (Blueprint $table): void {
            $table->dropColumn(['local_players', 'online_players']);
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['genres', 'modes', 'steam_details_at']);
        });
    }
};
