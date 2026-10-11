<?php

/**
 * Konfigurace Pestu a sdílené pomocné funkce testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Uložený účet s heslem UserFactory::PASSWORD.
 *
 * @param  array<string, mixed>  $attributes
 */
function user(array $attributes = []): User
{
    return User::factory()->create($attributes);
}
