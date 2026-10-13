<?php

/**
 * Odchod hry z katalogu PS Plus (R49).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * current_prices.member_leaves_at — kdy hra odchází z katalogu PS Plus
 * Extra / Premium (endTime tlačítka katalogu na stránce produktu PS Store).
 * Doplní ho nejbližší obnova cen.
 */
return new class extends Migration
{
    /**
     * Přidá sloupec.
     */
    public function up(): void
    {
        Schema::table('current_prices', function (Blueprint $table): void {
            $table->dateTime('member_leaves_at')->nullable()->after('member_free')
                ->comment('Kdy hra odchází z katalogu PS Plus (UTC); NULL = zůstává nebo v něm není');
        });
    }

    /**
     * Odstraní sloupec.
     */
    public function down(): void
    {
        Schema::table('current_prices', function (Blueprint $table): void {
            $table->dropColumn('member_leaves_at');
        });
    }
};
