<?php

/**
 * Založení uživatelského účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Účty zakládá jen správce — registrace není (R4). Lokálně se účet uloží
 * rovnou, pro produkci (bez SSH a artisanu) vznikne SQL příkaz s hashem
 * hesla, který se pustí v phpMyAdminu.
 */
final class CreateUser
{
    /**
     * Uloží nový účet. Vstup je ověřený na hranici (příkaz).
     */
    public function handle(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * SQL příkaz, který založí stejný účet na produkci. Heslo v něm je jen
     * jako hash; hodnoty escapuje PDO aktuálního připojení.
     */
    public function toSql(string $name, string $email, string $password): string
    {
        $pdo = DB::connection()->getPdo();
        $now = Carbon::now('UTC')->toDateTimeString();

        $values = array_map(
            fn (string $value): string => (string) $pdo->quote($value),
            [$name, $email, Hash::make($password), $now, $now],
        );

        return 'INSERT INTO `users` (`name`, `email`, `password`, `created_at`, `updated_at`) VALUES ('
            .implode(', ', $values).');';
    }
}
