<?php

/**
 * Stav zdrojů — kdy se který krok cronu naposledy povedl (R29).
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
 * Jeden řádek na hlídaný zdroj (enum MonitoredSource), přepisuje ho každý běh
 * cronu — historie běhů se nevede, podrobnosti chyb jsou v logu.
 */
return new class extends Migration
{
    private const SOURCE_LENGTH = 32;

    private const ERROR_LENGTH = 255;

    /**
     * Vytvoří tabulku stavu zdrojů.
     */
    public function up(): void
    {
        Schema::create('source_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('source', self::SOURCE_LENGTH)->unique()->comment('Zdroj nebo krok cronu (enum MonitoredSource)');
            $table->timestamp('last_run_at')->nullable()->comment('Poslední pokus (UTC)');
            $table->timestamp('last_success_at')->nullable()->comment('Poslední úspěšný pokus (UTC)');
            $table->timestamp('last_failure_at')->nullable()->comment('Poslední neúspěšný pokus (UTC)');
            $table->string('last_error', self::ERROR_LENGTH)->nullable()->comment('Zpráva poslední chyby (bez tajemství)');
            $table->unsignedInteger('failures_in_row')->default(0)->comment('Neúspěšné pokusy od posledního úspěchu');
            $table->unsignedInteger('processed')->nullable()->comment('Počet zpracovaných položek při posledním úspěchu [her / produktů / upozornění]');
            $table->unsignedInteger('expected')->nullable()->comment('Kolik položek měl zdroj zpracovat; NULL = neurčeno');
        });
    }

    /**
     * Odstraní tabulku stavu zdrojů.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_statuses');
    }
};
