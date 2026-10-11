<?php

/**
 * Továrna testovacích účtů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Heslo všech účtů z továrny (testy se jím přihlašují). */
    public const PASSWORD = 'heslo-pro-testy';

    /** Délka náhodného tokenu „zapamatovat si mě". */
    private const REMEMBER_TOKEN_LENGTH = 10;

    /** Hash hesla se počítá jen jednou — bcrypt je záměrně pomalý. */
    protected static ?string $passwordHash = null;

    /**
     * Výchozí hodnoty účtu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
        ];
    }
}
