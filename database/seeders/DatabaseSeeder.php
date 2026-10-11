<?php

/**
 * Výchozí seeder — záměrně prázdný.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Účty se nezakládají seederem s pevným heslem, ale příkazem
 * `games:create-user` (R4) — i lokálně, aby se postup nelišil od produkce.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Nic nezakládá.
     */
    public function run(): void
    {
        //
    }
}
