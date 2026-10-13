<?php

/**
 * Úroveň katalogu PS Plus a měsíční hry (R35), týdenní souhrn e-mailem (R36).
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
 * current_prices — v jaké úrovni PS Plus je hra v katalogu (Extra / Premium)
 * a jestli je měsíční hrou Essential; users — volba týdenního souhrnu a kdy
 * odešel naposledy.
 */
return new class extends Migration
{
    private const TIER_LENGTH = 16;

    /**
     * Přidá sloupce.
     */
    public function up(): void
    {
        Schema::table('current_prices', function (Blueprint $table): void {
            $table->string('member_tier', self::TIER_LENGTH)->nullable()->after('member_included')
                ->comment('Úroveň PS Plus katalogu (enum PlusTier: extra, premium); NULL = neuvedeno');
            $table->boolean('member_free')->default(false)->after('member_tier')
                ->comment('Měsíční hra PS Plus Essential — s předplatným zdarma');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notify_weekly')->default(true)->after('notify_email')
                ->comment('Posílat týdenní souhrn slev e-mailem');
            $table->timestamp('weekly_digest_at')->nullable()->after('notify_weekly')
                ->comment('Kdy odešel poslední týdenní souhrn (UTC)');
        });
    }

    /**
     * Odstraní sloupce.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['notify_weekly', 'weekly_digest_at']);
        });

        Schema::table('current_prices', function (Blueprint $table): void {
            $table->dropColumn(['member_tier', 'member_free']);
        });
    }
};
