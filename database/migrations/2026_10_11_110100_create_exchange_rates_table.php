<?php

/**
 * Denní kurzy ČNB pro převod cen (etapa 3, R14).
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
 * Jeden řádek na měnu a den vyhlášení lístku; kurz je přepočtený na jednu
 * jednotku měny (ČNB některé uvádí za 100 nebo 1000 jednotek).
 */
return new class extends Migration
{
    private const CURRENCY_LENGTH = 3;

    /** decimal(14,6) — kurz s přesností na miliontiny. */
    private const RATE_PRECISION = 14;

    private const RATE_SCALE = 6;

    /**
     * Vytvoří tabulku kurzů.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->date('day')->comment('Den vyhlášení kurzu ČNB');
            $table->char('currency', self::CURRENCY_LENGTH)->comment('Kód měny (ISO 4217)');
            $table->decimal('rate', self::RATE_PRECISION, self::RATE_SCALE)->comment('Kurz [CZK za 1 jednotku měny]');
            $table->primary(['day', 'currency']);
        });
    }

    /**
     * Odstraní tabulku kurzů.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
