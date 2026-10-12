<?php

/**
 * Hlídání cen — cílová cena, nastavení upozornění a odběry Web Push (etapa 6, R13).
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
 * watchlist_items — cílová cena v měně, kterou uživatel zadal, a poslední
 * upozorněná cena (stejnou cenu podruhé neposílat, R26); users — kanál e-mail
 * a předplatné PS Plus; push_subscriptions — zařízení přihlášeného uživatele.
 */
return new class extends Migration
{
    private const AMOUNT_PRECISION = 10;

    private const AMOUNT_SCALE = 2;

    private const CURRENCY_LENGTH = 3;

    /** Délka hashe SHA-256 v hex. */
    private const HASH_LENGTH = 64;

    private const KEY_LENGTH = 255;

    private const ENCODING_LENGTH = 16;

    /**
     * Přidá sloupce a tabulku odběrů.
     */
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->decimal('target_amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->nullable()->after('platform')
                ->comment('Cílová cena [target_currency]; NULL = upozornit na historické minimum');
            $table->char('target_currency', self::CURRENCY_LENGTH)->nullable()->after('target_amount')
                ->comment('Měna cílové ceny (měna zobrazení účtu při zadání)');
            $table->decimal('notified_amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->nullable()->after('target_currency')
                ->comment('Cena z posledního upozornění [notified_currency]; NULL = podmínka neplatí');
            $table->char('notified_currency', self::CURRENCY_LENGTH)->nullable()->after('notified_amount')
                ->comment('Měna ceny z posledního upozornění');
            $table->timestamp('notified_at')->nullable()->after('notified_currency')
                ->comment('Kdy odešlo poslední upozornění');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notify_email')->default(true)->after('display_currency')
                ->comment('Posílat upozornění na cenu e-mailem');
            $table->boolean('has_ps_plus')->default(false)->after('notify_email')
                ->comment('Má PS Plus — upozornění počítají cenu pro předplatitele');
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint')->comment('Adresa odběru u push služby prohlížeče');
            // Adresa je delší, než dovolí unikátní index — unikátnost hlídá její hash
            $table->char('endpoint_hash', self::HASH_LENGTH)->unique()->comment('SHA-256 adresy odběru');
            $table->string('public_key', self::KEY_LENGTH)->comment('Veřejný klíč prohlížeče pro šifrování (p256dh)');
            $table->string('auth_token', self::KEY_LENGTH)->comment('Tajemství prohlížeče pro šifrování (auth)');
            $table->string('content_encoding', self::ENCODING_LENGTH)->comment('Kódování obsahu zprávy (aes128gcm)');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku a sloupce.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['notify_email', 'has_ps_plus']);
        });

        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropColumn(['target_amount', 'target_currency', 'notified_amount', 'notified_currency', 'notified_at']);
        });
    }
};
