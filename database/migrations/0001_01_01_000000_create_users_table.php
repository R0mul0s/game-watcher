<?php

/**
 * Tabulka uživatelských účtů (R4).
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
 * Účty zakládá správce (games:create-user, na produkci SQL skriptem) — bez
 * registrace a bez obnovy hesla e-mailem, proto tu chybí password_reset_tokens.
 * Session je v cookie (R5), tabulka sessions také není.
 */
return new class extends Migration
{
    /**
     * Vytvoří tabulku účtů.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Zobrazované jméno');
            $table->string('email')->unique()->comment('Přihlašovací e-mail');
            $table->string('password')->comment('Hash hesla (bcrypt)');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku účtů.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
