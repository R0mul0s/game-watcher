<?php

/**
 * Zakládání účtů příkazem games:create-user (R4).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Accounts\Actions\CreateUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const STRONG_PASSWORD = 'dlouhe-heslo-123';

it('založí účet s heslem zadaným skrytě', function (): void {
    $this->artisan('games:create-user', ['email' => 'hrac@example.com', '--name' => 'Hráč'])
        ->expectsQuestion(__('app.users.password_prompt'), STRONG_PASSWORD)
        ->expectsQuestion(__('app.users.password_confirm_prompt'), STRONG_PASSWORD)
        ->expectsOutputToContain(__('app.users.created', ['email' => 'hrac@example.com']))
        ->assertSuccessful();

    $user = User::query()->sole();
    expect($user->name)->toBe('Hráč')
        ->and(Hash::check(STRONG_PASSWORD, $user->getAuthPassword()))->toBeTrue();
});

it('bez jména použije část e-mailu před zavináčem', function (): void {
    $this->artisan('games:create-user', ['email' => 'roman@example.com'])
        ->expectsQuestion(__('app.users.password_prompt'), STRONG_PASSWORD)
        ->expectsQuestion(__('app.users.password_confirm_prompt'), STRONG_PASSWORD)
        ->assertSuccessful();

    expect(User::query()->sole()->name)->toBe('roman');
});

it('odmítne krátké heslo', function (): void {
    $this->artisan('games:create-user', ['email' => 'hrac@example.com'])
        ->expectsQuestion(__('app.users.password_prompt'), 'kratke')
        ->expectsOutputToContain(__('app.users.password_too_short', ['min' => config('games.auth.min_password_length')]))
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('odmítne neshodu hesel', function (): void {
    $this->artisan('games:create-user', ['email' => 'hrac@example.com'])
        ->expectsQuestion(__('app.users.password_prompt'), STRONG_PASSWORD)
        ->expectsQuestion(__('app.users.password_confirm_prompt'), 'jine-dlouhe-heslo')
        ->expectsOutputToContain(__('app.users.password_mismatch'))
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('odmítne e-mail, který už má účet', function (): void {
    user(['email' => 'hrac@example.com']);

    $this->artisan('games:create-user', ['email' => 'hrac@example.com'])
        ->expectsQuestion(__('app.users.password_prompt'), STRONG_PASSWORD)
        ->expectsOutputToContain(__('app.users.email_taken'))
        ->assertFailed();
});

it('s --sql účet neuloží a vypíše INSERT s hashem hesla pro produkci', function (): void {
    $this->artisan('games:create-user', ['email' => 'hrac@example.com', '--name' => "O'Brien", '--sql' => true])
        ->expectsQuestion(__('app.users.password_prompt'), STRONG_PASSWORD)
        ->expectsQuestion(__('app.users.password_confirm_prompt'), STRONG_PASSWORD)
        ->expectsOutputToContain('INSERT INTO `users`')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

it('SQL z --sql založí na produkci účet, se kterým jde přihlásit', function (): void {
    $sql = app(CreateUser::class)->toSql("O'Brien", 'hrac@example.com', STRONG_PASSWORD);

    expect($sql)->not->toContain(STRONG_PASSWORD);
    DB::unprepared($sql);

    $this->post('/prihlaseni', ['email' => 'hrac@example.com', 'password' => STRONG_PASSWORD])
        ->assertRedirect('/');
    expect(User::query()->sole()->name)->toBe("O'Brien");
});
