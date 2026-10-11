<?php

/**
 * Osobní seznam sledovaných her (etapa 2, R11).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Každý účet má vlastní seznam; hra se smazáním z účtu z katalogu nemizí
 * (sleduje ji případně někdo jiný a má historii cen). Cílová cena přibude
 * s hlídáním cen (etapa 6).
 */
return new class extends Migration
{
    /**
     * Vytvoří tabulku sledovaných her.
     */
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'game_id']);
        });
    }

    /**
     * Odstraní tabulku sledovaných her.
     */
    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
    }
};
