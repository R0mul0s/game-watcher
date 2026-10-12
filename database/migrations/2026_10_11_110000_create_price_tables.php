<?php

/**
 * Ceny her — aktuální nabídky, historická minima a denní historie (etapa 3, R9).
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
 * current_prices — poslední cena hry v každém obchodě (ITAD) a nejnižší cena
 * obchodů s klíči (gg.deals); price_lows — historická minima; price_history —
 * nejnižší cena za den po obchodech (podklad grafu, etapa 7). Částky v měně
 * zdroje (R14), převod až při zobrazení.
 */
return new class extends Migration
{
    /** Klíč obchodu: itad-<id obchodu> nebo keyshops. */
    private const SHOP_KEY_LENGTH = 32;

    private const SHOP_NAME_LENGTH = 64;

    private const SOURCE_LENGTH = 16;

    private const SCOPE_LENGTH = 16;

    private const CURRENCY_LENGTH = 3;

    private const URL_LENGTH = 500;

    /** decimal(10,2) — částky do 99 999 999,99. */
    private const AMOUNT_PRECISION = 10;

    private const AMOUNT_SCALE = 2;

    /**
     * Vytvoří tabulky cen.
     */
    public function up(): void
    {
        Schema::create('current_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('source', self::SOURCE_LENGTH)->comment('Zdroj ceny (enum PriceSource: itad, ggdeals)');
            $table->string('shop_key', self::SHOP_KEY_LENGTH)->comment('Obchod: itad-<id obchodu v ITAD>, keyshops = nejnižší z obchodů s klíči');
            $table->string('shop_name', self::SHOP_NAME_LENGTH)->comment('Název obchodu ze zdroje');
            $table->decimal('amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->comment('Aktuální cena [měna ve sloupci currency]');
            $table->decimal('regular_amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->nullable()->comment('Běžná cena bez slevy [currency]');
            $table->char('currency', self::CURRENCY_LENGTH)->comment('Měna ceny (ISO 4217)');
            $table->unsignedTinyInteger('cut')->default(0)->comment('Sleva [%]');
            $table->string('drm', self::SHOP_NAME_LENGTH)->nullable()->comment('Kde se hra aktivuje (Steam, Epic, DRM Free…)');
            $table->string('url', self::URL_LENGTH)->nullable()->comment('Odkaz do obchodu (partnerský odkaz ITAD se nesmí měnit)');
            $table->dateTime('expires_at')->nullable()->comment('Konec slevy (UTC), když ho obchod uvádí');
            $table->dateTime('fetched_at')->comment('Kdy se cena stáhla (UTC)');
            $table->unique(['game_id', 'shop_key']);
        });

        Schema::create('price_lows', function (Blueprint $table): void {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('scope', self::SCOPE_LENGTH)->comment('official = oficiální obchody (ITAD), keyshops = obchody s klíči (gg.deals)');
            $table->decimal('amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->comment('Historicky nejnižší cena [currency]');
            $table->char('currency', self::CURRENCY_LENGTH)->comment('Měna (ISO 4217)');
            $table->dateTime('fetched_at')->comment('Kdy se minimum stáhlo (UTC)');
            $table->primary(['game_id', 'scope']);
        });

        Schema::create('price_history', function (Blueprint $table): void {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('shop_key', self::SHOP_KEY_LENGTH)->comment('Obchod jako v current_prices');
            $table->date('day')->comment('Místní den (Europe/Prague)');
            $table->decimal('amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->comment('Nejnižší cena dne [currency]');
            $table->char('currency', self::CURRENCY_LENGTH)->comment('Měna (ISO 4217)');
            $table->primary(['game_id', 'shop_key', 'day']);
        });
    }

    /**
     * Odstraní tabulky cen.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('price_lows');
        Schema::dropIfExists('current_prices');
    }
};
