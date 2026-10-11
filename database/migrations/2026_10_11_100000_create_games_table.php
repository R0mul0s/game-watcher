<?php

/**
 * Katalog her převzatý z IsThereAnyDeal (etapa 2, R10).
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
 * Identitou hry je ITAD ID (UUID). Steam App ID je druhotný klíč pro zdroje,
 * které hru znají jen podle Steamu (gg.deals) — hry mimo Steam ho nemají.
 * Katalog je společný pro všechny účty (R11).
 */
return new class extends Migration
{
    /** Délka UUID v textové podobě. */
    private const UUID_LENGTH = 36;

    /** Nejdelší druh hry z ITAD (game, dlc, package…). */
    private const TYPE_LENGTH = 16;

    /** Adresy obrázků z CDN ITAD včetně parametru verze. */
    private const URL_LENGTH = 500;

    /**
     * Vytvoří tabulku her.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->char('itad_id', self::UUID_LENGTH)->unique()->comment('ID hry v IsThereAnyDeal (UUID) — identita hry');
            $table->string('slug')->unique()->comment('Slug z ITAD — adresa detailu hry');
            $table->string('title')->comment('Název hry');
            $table->string('type', self::TYPE_LENGTH)->comment('Druh z ITAD: game, dlc…');
            $table->boolean('is_mature')->default(false)->comment('Obsah pro dospělé podle ITAD');
            $table->unsignedInteger('steam_app_id')->nullable()->index()->comment('Steam App ID — prázdné u her mimo Steam');
            $table->date('release_date')->nullable()->comment('Datum vydání');
            $table->json('developers')->nullable()->comment('Vývojáři — seznam názvů');
            $table->json('publishers')->nullable()->comment('Vydavatelé — seznam názvů');
            $table->json('tags')->nullable()->comment('Štítky ze Steamu přes ITAD');
            $table->json('reviews')->nullable()->comment('Hodnocení: zdroj, skóre 0–100, počet, odkaz');
            $table->string('boxart_url', self::URL_LENGTH)->nullable()->comment('Obal hry (CDN ITAD)');
            $table->string('banner_url', self::URL_LENGTH)->nullable()->comment('Široký banner (CDN ITAD)');
            $table->string('itad_url', self::URL_LENGTH)->nullable()->comment('Stránka hry na isthereanydeal.com');
            $table->dateTime('info_refreshed_at')->comment('Kdy se údaje naposledy převzaly z ITAD (UTC)');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku her.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
