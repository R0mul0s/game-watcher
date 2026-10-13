<?php

/**
 * Upozornění na vydání a první slevu sledované hry (R41).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * watchlist_items — kdy odešlo upozornění „právě vyšlo“ a „první sleva od
 * vydání“. Hry, které už vyšly, se označí jako upozorněné — týkají se jen her
 * sledovaných před vydáním.
 */
return new class extends Migration
{
    /**
     * Přidá sloupce a označí vydané hry.
     */
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->timestamp('release_notified_at')->nullable()->after('notified_at')
                ->comment('Kdy odešlo upozornění na vydání; NULL = hra ještě nevyšla nebo se čeká');
            $table->timestamp('first_sale_notified_at')->nullable()->after('release_notified_at')
                ->comment('Kdy odešlo upozornění na první slevu od vydání; NULL = čeká se');
        });

        $now = CarbonImmutable::now();
        DB::table('watchlist_items')
            ->whereIn('game_id', DB::table('games')->select('id')->where('release_date', '<=', $now->toDateString()))
            ->update(['release_notified_at' => $now, 'first_sale_notified_at' => $now]);
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropColumn(['release_notified_at', 'first_sale_notified_at']);
        });
    }
};
