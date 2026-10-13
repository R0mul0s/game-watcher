<?php

/**
 * Nadcházející hry z IGDB (R46).
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
 * Seznam pro záložku Objevit — mimo katalog `games` (identita hry je ITAD ID,
 * R10). Cron ho celý přepisuje z IGDB; ITAD ID se doplní, až hru někdo začne
 * sledovat. Vydání po platformách (PC, PlayStation): datum je den uvnitř
 * období v přesnosti zdroje (Q1 2027 = 31. 3. 2027, enum ReleasePrecision).
 */
return new class extends Migration
{
    private const UUID_LENGTH = 36;

    private const IMAGE_ID_LENGTH = 64;

    private const URL_LENGTH = 512;

    private const PRECISION_LENGTH = 16;

    /**
     * Vytvoří tabulku nadcházejících her.
     */
    public function up(): void
    {
        Schema::create('upcoming_releases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('igdb_id')->unique()->comment('ID hry v IGDB');
            $table->string('title')->comment('Název hry podle IGDB');
            $table->string('igdb_url', self::URL_LENGTH)->nullable()->comment('Stránka hry na igdb.com');
            $table->unsignedInteger('hypes')->default(0)->comment('Popularita před vydáním podle IGDB (počet sledujících)');
            $table->unsignedInteger('steam_app_id')->nullable()->index()->comment('Steam App ID z IGDB — párování s ITAD');
            $table->char('itad_id', self::UUID_LENGTH)->nullable()->index()->comment('ID hry v IsThereAnyDeal po spárování; NULL = ještě nepárováno');
            $table->string('cover_image_id', self::IMAGE_ID_LENGTH)->nullable()->comment('Obal — image_id v IGDB');
            $table->string('banner_image_id', self::IMAGE_ID_LENGTH)->nullable()->comment('Banner (artwork nebo snímek) — image_id v IGDB');
            $table->json('genres')->nullable()->comment('Žánry podle IGDB — seznam názvů');
            $table->boolean('coop')->default(false)->comment('Hraní ve víc lidech spolu (kooperace, rozdělená obrazovka)');
            $table->date('pc_release_on')->nullable()->comment('Nadcházející vydání na PC; NULL = na PC nevychází nebo už vyšla');
            $table->string('pc_release_precision', self::PRECISION_LENGTH)->nullable()->comment('Přesnost data na PC (enum ReleasePrecision)');
            $table->boolean('pc_early_access')->default(false)->comment('Na PC jde o start předběžného přístupu');
            $table->date('playstation_release_on')->nullable()->comment('Nadcházející vydání na PS5; NULL = na PS5 nevychází nebo už vyšla');
            $table->string('playstation_release_precision', self::PRECISION_LENGTH)->nullable()->comment('Přesnost data na PS5 (enum ReleasePrecision)');
            $table->boolean('playstation_early_access')->default(false)->comment('Na PS5 jde o start předběžného přístupu');
            $table->date('next_release_on')->index()->comment('Nejbližší z vydání na platformách — řazení podle data');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku nadcházejících her.
     */
    public function down(): void
    {
        Schema::dropIfExists('upcoming_releases');
    }
};
