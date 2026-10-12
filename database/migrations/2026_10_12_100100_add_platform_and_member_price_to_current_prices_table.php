<?php

/**
 * Platforma, edice a cena pro předplatitele u cen (etapa 5, R20).
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
 * PlayStation Store patří do stejného výpisu cen jako obchody pro PC, ale jako
 * samostatné položky s platformou a edicí. Vedle běžné ceny má i cenu s PS Plus
 * a informaci, že je hra v katalogu předplatného (zdarma pro předplatitele).
 */
return new class extends Migration
{
    private const PLATFORM_LENGTH = 16;

    private const EDITION_LENGTH = 160;

    /** decimal(10,2) — jako ostatní částky. */
    private const AMOUNT_PRECISION = 10;

    private const AMOUNT_SCALE = 2;

    /**
     * Přidá sloupce.
     */
    public function up(): void
    {
        Schema::table('current_prices', function (Blueprint $table): void {
            $table->string('platform', self::PLATFORM_LENGTH)->default('pc')->after('shop_name')
                ->comment('Platforma (enum Platform: pc, playstation)');
            $table->string('edition', self::EDITION_LENGTH)->nullable()->after('platform')
                ->comment('Název produktu v obchodě, když ho obchod uvádí (edice)');
            $table->decimal('member_amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->nullable()->after('regular_amount')
                ->comment('Cena pro předplatitele (PS Plus) [currency]');
            $table->boolean('member_included')->default(false)->after('member_amount')
                ->comment('Hra je v katalogu předplatného (PS Plus Extra) — pro předplatitele zdarma');
        });
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('current_prices', function (Blueprint $table): void {
            $table->dropColumn(['platform', 'edition', 'member_amount', 'member_included']);
        });
    }
};
