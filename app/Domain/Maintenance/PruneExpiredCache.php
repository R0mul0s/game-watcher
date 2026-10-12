<?php

/**
 * Úklid prošlých záznamů databázové cache.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Maintenance;

use Illuminate\Support\Facades\DB;

/**
 * DatabaseStore prošlé řádky sám nemaže a scheduler hosting nemá (R2) — bez
 * úklidu by tabulka rostla s každým hledáním (R15). Volá ho cron URL
 * (stejně jako v projektu Počasí).
 */
final class PruneExpiredCache
{
    private const CACHE_TABLE = 'cache';

    private const LOCK_TABLE = 'cache_locks';

    /**
     * Smaže prošlé položky cache a zámků; vrátí počet smazaných řádků.
     */
    public function handle(): int
    {
        $now = time();

        return DB::table(self::CACHE_TABLE)->where('expiration', '<', $now)->delete()
            + DB::table(self::LOCK_TABLE)->where('expiration', '<', $now)->delete();
    }
}
