<?php

/**
 * Přihlášení a odhlášení (R3, R4).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia as Assert;

it('pošle nepřihlášeného na přihlášení', function (): void {
    $this->get('/')->assertRedirect('/prihlaseni');
});

it('zobrazí přihlašovací stránku', function (): void {
    $this->get('/prihlaseni')
        ->assertOk()
        ->assertSee('noindex, nofollow', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('loginUrl', '/prihlaseni')
            ->where('auth', null));
});

it('přihlásí uživatele se správným heslem a pošle ho na přehled', function (): void {
    $user = user();

    $this->post('/prihlaseni', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('přihlásí i s e-mailem psaným jinou velikostí písmen', function (): void {
    $user = user(['email' => 'hrac@example.com']);

    $this->post('/prihlaseni', ['email' => 'Hrac@Example.com', 'password' => UserFactory::PASSWORD]);

    $this->assertAuthenticatedAs($user);
});

it('odmítne špatné heslo', function (): void {
    $user = user();

    $this->from('/prihlaseni')
        ->post('/prihlaseni', ['email' => $user->email, 'password' => 'spatne-heslo'])
        ->assertRedirect('/prihlaseni')
        ->assertSessionHasErrors(['email' => __('app.auth.failed')]);

    $this->assertGuest();
});

it('vyžaduje e-mail a heslo', function (): void {
    $this->post('/prihlaseni', [])
        ->assertSessionHasErrors([
            'email' => __('app.auth.email_required'),
            'password' => __('app.auth.password_required'),
        ]);
});

it('po vyčerpání pokusů přihlášení zablokuje i se správným heslem', function (): void {
    $user = user();
    $attempts = (int) config('games.auth.max_attempts');

    for ($i = 0; $i < $attempts; $i++) {
        $this->post('/prihlaseni', ['email' => $user->email, 'password' => 'spatne-heslo']);
    }

    $this->post('/prihlaseni', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Příliš mnoho pokusů');
    $this->assertGuest();
});

it('pošle přihlášeného z přihlašovací stránky na přehled', function (): void {
    $this->actingAs(user())
        ->get('/prihlaseni')
        ->assertRedirect('/');
});

it('ukáže přihlášenému přehled s jeho jménem a navigací', function (): void {
    $this->actingAs(user(['name' => 'Roman']))
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.name', 'Roman')
            ->where('auth.logoutUrl', '/odhlaseni')
            ->where('navigation.0.url', '/')
            ->where('navigation.0.active', true)
            ->has('translations.dashboard'));
});

it('odhlásí uživatele', function (): void {
    $this->actingAs(user())
        ->post('/odhlaseni')
        ->assertRedirect('/prihlaseni');

    $this->assertGuest();
});

it('nemá registraci ani obnovu hesla', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with(['/registrace', '/register', '/forgot-password', '/zapomenute-heslo']);
