<?php

/**
 * Délka hraní z HowLongToBeat (R44).
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
 * games — hra na HowLongToBeat a průměrné délky hraní v minutách (hlavní
 * příběh, s vedlejšími úkoly, na 100 %), kdy se HLTB naposledy zkoušel.
 */
return new class extends Migration
{
    /**
     * Přidá sloupce.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedInteger('hltb_id')->nullable()->after('metacritic_checked_at')
                ->comment('Hra na HowLongToBeat (howlongtobeat.com/game/<id>); NULL = nenalezena');
            $table->unsignedInteger('hltb_main_minutes')->nullable()->after('hltb_id')
                ->comment('Hlavní příběh podle HLTB [min]');
            $table->unsignedInteger('hltb_extra_minutes')->nullable()->after('hltb_main_minutes')
                ->comment('Příběh s vedlejšími úkoly podle HLTB [min]');
            $table->unsignedInteger('hltb_complete_minutes')->nullable()->after('hltb_extra_minutes')
                ->comment('Na 100 % podle HLTB [min]');
            $table->timestamp('hltb_checked_at')->nullable()->after('hltb_complete_minutes')
                ->comment('Kdy se HowLongToBeat naposledy zkoušel (UTC); NULL = ještě ne');
        });
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['hltb_id', 'hltb_main_minutes', 'hltb_extra_minutes', 'hltb_complete_minutes', 'hltb_checked_at']);
        });
    }
};
