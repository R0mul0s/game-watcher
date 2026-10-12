<?php

/**
 * Propojení hry s konkrétním produktem v obchodě bez ITAD (etapa 5, R10, R20).
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
 * Produkt se ke hře připojuje vložením odkazu, ne hledáním podle názvu (R10) —
 * obchod má pro jednu hru víc produktů (PS4, PS5, edice). Propojení je
 * součást společného katalogu (R11).
 */
return new class extends Migration
{
    private const SOURCE_LENGTH = 16;

    /** ID produktu PlayStation Store má 36 znaků (EP5291-PPSA34547_00-0210080241603648). */
    private const EXTERNAL_ID_LENGTH = 64;

    private const NAME_LENGTH = 160;

    private const PLATFORMS_LENGTH = 32;

    private const URL_LENGTH = 500;

    /**
     * Vytvoří tabulku propojení s obchody.
     */
    public function up(): void
    {
        Schema::create('store_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('source', self::SOURCE_LENGTH)->comment('Obchod (enum PriceSource: playstation)');
            $table->string('external_id', self::EXTERNAL_ID_LENGTH)->comment('ID produktu v obchodě');
            $table->string('name', self::NAME_LENGTH)->comment('Název produktu v obchodě (edice)');
            $table->string('platforms', self::PLATFORMS_LENGTH)->nullable()->comment('Platformy produktu (PS5, PS4)');
            $table->string('url', self::URL_LENGTH)->comment('Stránka produktu v obchodě');
            $table->timestamps();
            $table->unique(['game_id', 'source', 'external_id']);
        });
    }

    /**
     * Odstraní tabulku propojení s obchody.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_listings');
    }
};
