<?php

/**
 * Game Pass a bundly z IsThereAnyDeal (R50, R51).
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
 * games — hra v Game Passu a kdy z něj odchází (přepisuje cron);
 * users — účet má Game Pass (upozornění jen pro něj);
 * watchlist_items — stav „už upozorněno“ na Game Pass a na bundly;
 * game_bundles — aktivní bundly s hrami katalogu, celou ji přepisuje cron.
 */
return new class extends Migration
{
    private const BUNDLE_TITLE_LENGTH = 255;

    private const SHOP_NAME_LENGTH = 64;

    private const URL_LENGTH = 500;

    private const CURRENCY_LENGTH = 3;

    /** decimal(10,2) — jako ceny v current_prices. */
    private const AMOUNT_PRECISION = 10;

    private const AMOUNT_SCALE = 2;

    /**
     * Přidá sloupce a tabulku.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->boolean('in_game_pass')->default(false)->after('czech')
                ->comment('Hra je v Game Passu podle ITAD (R50)');
            $table->dateTime('game_pass_leaves_at')->nullable()->after('in_game_pass')
                ->comment('Kdy hra z Game Passu odchází (UTC); NULL = neuvedeno');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_game_pass')->default(false)->after('has_ps_plus')
                ->comment('Účet má Game Pass — upozornění na hry v něm (R50)');
        });

        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->timestamp('game_pass_notified_at')->nullable()->after('first_sale_notified_at')
                ->comment('Kdy odešlo upozornění „je v Game Passu“; NULL = ještě ne nebo hra z Game Passu odešla');
            $table->json('notified_bundles')->nullable()->after('game_pass_notified_at')
                ->comment('ID bundlů ITAD, na které už upozornění odešlo (R51)');
        });

        Schema::create('game_bundles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('bundle_id')->comment('ID bundlu v ITAD');
            $table->string('title', self::BUNDLE_TITLE_LENGTH);
            $table->string('shop', self::SHOP_NAME_LENGTH)->comment('Obchod s bundlem (Humble Bundle, Fanatical…)');
            $table->string('url', self::URL_LENGTH)->comment('Partnerský odkaz ITAD — neupravovat');
            $table->decimal('amount', self::AMOUNT_PRECISION, self::AMOUNT_SCALE)->nullable()
                ->comment('Cena nejlevnější úrovně s hrou; NULL = „Build your own“ bez ceny');
            $table->string('currency', self::CURRENCY_LENGTH)->nullable();
            $table->dateTime('expires_at')->nullable()->comment('Konec bundlu (UTC); NULL = neuveden');
            $table->unique(['game_id', 'bundle_id']);
            $table->comment('Aktivní bundly s hrami katalogu z ITAD (R51) — celou ji přepisuje cron');
        });
    }

    /**
     * Odstraní tabulku a sloupce.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_bundles');

        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dropColumn(['game_pass_notified_at', 'notified_bundles']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('has_game_pass');
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['in_game_pass', 'game_pass_leaves_at']);
        });
    }
};
