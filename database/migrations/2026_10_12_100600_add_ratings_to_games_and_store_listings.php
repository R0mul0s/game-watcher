<?php

/**
 * Metascore z Metacritic pro hry bez hodnocení v ITAD a hvězdičky hráčů z PS Store (R33).
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
 * games — stránka hry na Metacritic a její Metascore (exkluzivity PS5 a další
 * hry, kterým ITAD Metascore nedává); store_listings — průměr hodnocení hráčů
 * produktu na PS Store a počet hodnocení.
 */
return new class extends Migration
{
    private const SLUG_LENGTH = 120;

    /** decimal(3,2) — hvězdičky 0,00 až 5,00. */
    private const STAR_PRECISION = 3;

    private const STAR_SCALE = 2;

    /**
     * Přidá sloupce hodnocení.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('metacritic_slug', self::SLUG_LENGTH)->nullable()->after('reviews')
                ->comment('Stránka hry na Metacritic (metacritic.com/game/<slug>/); NULL = nenalezena');
            $table->unsignedTinyInteger('metacritic_score')->nullable()->after('metacritic_slug')
                ->comment('Metascore z Metacritic [0–100]; NULL = bez hodnocení kritiků');
            $table->unsignedInteger('metacritic_reviews')->nullable()->after('metacritic_score')
                ->comment('Počet recenzí kritiků za Metascore');
            $table->timestamp('metacritic_checked_at')->nullable()->after('metacritic_reviews')
                ->comment('Kdy se Metacritic naposledy zkoušel (UTC); NULL = ještě ne');
        });

        Schema::table('store_listings', function (Blueprint $table): void {
            $table->decimal('star_rating', self::STAR_PRECISION, self::STAR_SCALE)->nullable()->after('online_players')
                ->comment('Průměr hodnocení hráčů na PS Store [hvězdičky 0–5]');
            $table->unsignedInteger('star_ratings_count')->nullable()->after('star_rating')
                ->comment('Počet hodnocení hráčů na PS Store');
        });
    }

    /**
     * Odstraní sloupce hodnocení.
     */
    public function down(): void
    {
        Schema::table('store_listings', function (Blueprint $table): void {
            $table->dropColumn(['star_rating', 'star_ratings_count']);
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['metacritic_slug', 'metacritic_score', 'metacritic_reviews', 'metacritic_checked_at']);
        });
    }
};
