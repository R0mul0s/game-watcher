<?php

/**
 * Platforma, na které uživatel hru hlídá (R24).
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
 * Null = všude (výchozí); pc / playstation = karta, nejnižší cena a filtry
 * počítají jen s cenami té platformy. Patří sledování, ne hře — každý účet
 * může stejnou hru hlídat jinde.
 */
return new class extends Migration
{
    private const PLATFORM_LENGTH = 16;

    /**
     * Přidá sloupec.
     */
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->string('platform', self::PLATFORM_LENGTH)->nullable()->after('game_id')
                ->comment('Hlídaná platforma (enum Platform: pc, playstation); NULL = všude');
        });
    }

    /**
     * Odstraní sloupec.
     */
    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropColumn('platform');
        });
    }
};
