<?php

/**
 * Měna zobrazení cen u účtu (etapa 3, R14).
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
 * Volba přepínače CZK / EUR v hlavičce — u účtu, aby platila na všech zařízeních.
 */
return new class extends Migration
{
    private const CURRENCY_LENGTH = 3;

    /**
     * Přidá sloupec měny zobrazení.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('display_currency', self::CURRENCY_LENGTH)
                ->default('CZK')
                ->after('password')
                ->comment('Měna zobrazení cen (CZK, EUR)');
        });
    }

    /**
     * Odstraní sloupec měny zobrazení.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('display_currency');
        });
    }
};
