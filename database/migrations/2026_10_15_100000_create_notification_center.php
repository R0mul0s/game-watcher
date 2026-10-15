<?php

/**
 * Centrum upozornění a ranní souhrn e-mailem (R53).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * notifications — záznamy centra upozornění (databázové notifikace Laravelu);
 * users — jak často chodí e-mail s upozorněními (místo zapnuto/vypnuto)
 * a do kdy jsou záznamy centra poslané e-mailem.
 */
return new class extends Migration
{
    /** Délka sloupce users.email_frequency (hodnoty EmailFrequency). */
    private const FREQUENCY_LENGTH = 10;

    /**
     * Vytvoří tabulku záznamů a převede volbu e-mailu na frekvenci —
     * vypnutý e-mail zůstane vypnutý, zapnutý chodí nově jednou denně ráno.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->comment('Druh záznamu (NotificationKind)');
            $table->morphs('notifiable');
            $table->text('data')->comment('Obsah záznamu (JSON)');
            $table->timestamp('read_at')->nullable()->comment('Kdy uživatel záznam otevřel; NULL = nepřečtený');
            $table->timestamps();
            $table->comment('Centrum upozornění (R53) — záznamy starší než games.notifications.retention_days maže cron');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_frequency', self::FREQUENCY_LENGTH)->default('daily')->after('display_currency')
                ->comment('E-mail s upozorněními: daily = souhrn ráno, instant = po každém běhu cronu, off = neposílat');
            $table->timestamp('alert_email_sent_at')->nullable()->after('email_frequency')
                ->comment('Do kdy jsou záznamy centra poslané e-mailem (UTC); NULL = zatím nic');
        });

        DB::table('users')->where('notify_email', false)->update(['email_frequency' => 'off']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notify_email');
        });
    }

    /**
     * Vrátí přepínač e-mailu a odstraní tabulku záznamů.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notify_email')->default(true)->after('display_currency')
                ->comment('Posílat upozornění na cenu e-mailem');
        });

        DB::table('users')->where('email_frequency', 'off')->update(['notify_email' => false]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_frequency', 'alert_email_sent_at']);
        });

        Schema::dropIfExists('notifications');
    }
};
