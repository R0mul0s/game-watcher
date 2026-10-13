<?php

/**
 * Nastavení upozornění a zařízení s Web Push (etapa 6, R13).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('vyžaduje přihlášení', function (): void {
    $this->get('/nastaveni')->assertRedirect('/prihlaseni');
    $this->postJson('/nastaveni/zarizeni', browserSubscription())->assertUnauthorized();
});

it('ukáže nastavení — bez klíčů VAPID bez upozornění na zařízení', function (): void {
    $user = user(['email' => 'roman@example.com']);

    $this->actingAs($user)
        ->get('/nastaveni')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->where('settings.email', 'roman@example.com')
            ->where('settings.notifyEmail', true)
            ->where('settings.hasPsPlus', false)
            ->where('push', null));

    enablePush();
    $this->actingAs($user)
        ->get('/nastaveni')
        ->assertInertia(fn (Assert $page) => $page
            ->where('push.publicKey', config('games.push.public_key'))
            ->where('push.subscribeUrl', '/nastaveni/zarizeni'));
});

it('uloží e-mail, týdenní souhrn a PS Plus', function (): void {
    $user = user();

    $this->actingAs($user)
        ->from('/nastaveni')
        ->put('/nastaveni', ['notify_email' => false, 'notify_weekly' => false, 'has_ps_plus' => true])
        ->assertRedirect('/nastaveni');

    $user->refresh();
    expect($user->notify_email)->toBeFalse()
        ->and($user->notify_weekly)->toBeFalse()
        ->and($user->has_ps_plus)->toBeTrue();
});

it('uloží odběr zařízení k účtu a podruhé ho nezdvojí', function (): void {
    $user = user();
    $subscription = browserSubscription();

    $this->actingAs($user)->postJson('/nastaveni/zarizeni', $subscription)->assertOk()->assertExactJson(['subscribed' => true]);
    $this->actingAs($user)->postJson('/nastaveni/zarizeni', $subscription)->assertOk();

    $stored = PushSubscription::query()->sole();
    expect($stored->user_id)->toBe($user->id)
        ->and($stored->endpoint)->toBe($subscription['endpoint'])
        ->and($stored->public_key)->toBe($subscription['keys']['p256dh']);
});

it('zařízení jiného účtu převede na přihlášený', function (): void {
    $subscription = browserSubscription();
    $this->actingAs(user())->postJson('/nastaveni/zarizeni', $subscription);
    $other = user();

    $this->actingAs($other)->postJson('/nastaveni/zarizeni', $subscription)->assertOk();

    expect(PushSubscription::query()->sole()->user_id)->toBe($other->id);
});

it('odmítne odběr mimo známé push služby a vadné klíče', function (array $override, string $field): void {
    $this->actingAs(user())
        ->postJson('/nastaveni/zarizeni', [...browserSubscription(), ...$override])
        ->assertJsonValidationErrors($field);

    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'cizí server' => [['endpoint' => 'https://attacker.example.com/push'], 'endpoint'],
    'bez HTTPS' => [['endpoint' => 'http://fcm.googleapis.com/fcm/send/x'], 'endpoint'],
    'vadný klíč' => [['keys' => ['p256dh' => 'abc', 'auth' => 'abc']], 'keys.p256dh'],
    'kódování' => [['content_encoding' => 'gzip'], 'content_encoding'],
]);

it('omezí počet zařízení na účet', function (): void {
    config(['games.push.max_subscriptions_per_user' => 1]);
    $user = user();
    $this->actingAs($user)->postJson('/nastaveni/zarizeni', browserSubscription('https://fcm.googleapis.com/fcm/send/a'))->assertOk();

    $this->actingAs($user)->postJson('/nastaveni/zarizeni', browserSubscription('https://fcm.googleapis.com/fcm/send/b'))->assertUnprocessable();

    expect(PushSubscription::query()->count())->toBe(1);
});

it('odhlásí jen vlastní zařízení', function (): void {
    $subscription = browserSubscription();
    $owner = user();
    $this->actingAs($owner)->postJson('/nastaveni/zarizeni', $subscription);

    $this->actingAs(user())->deleteJson('/nastaveni/zarizeni', ['endpoint' => $subscription['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);

    $this->actingAs($owner)->deleteJson('/nastaveni/zarizeni', ['endpoint' => $subscription['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('pošle zkušební upozornění na vlastní zařízení', function (): void {
    enablePush();
    $subscription = browserSubscription();
    $user = user();
    $this->actingAs($user)->postJson('/nastaveni/zarizeni', $subscription);
    fakeApis(['fcm.googleapis.com/*' => Http::response('', 201)]);

    $this->actingAs($user)
        ->postJson('/nastaveni/zarizeni/test', ['endpoint' => $subscription['endpoint']])
        ->assertExactJson(['result' => 'sent']);
    Http::assertSent(fn (Request $request): bool => $request->url() === $subscription['endpoint'] && $request->header('Topic') === ['test']);

    $this->actingAs(user())
        ->postJson('/nastaveni/zarizeni/test', ['endpoint' => $subscription['endpoint']])
        ->assertNotFound();
});

it('nové účty mají e-mail zapnutý a PS Plus vypnuté', function (): void {
    $user = User::query()->findOrFail(user()->id);

    expect($user->notify_email)->toBeTrue()->and($user->has_ps_plus)->toBeFalse();
});

it('manifest aplikace a service worker jsou dostupné bez přihlášení', function (): void {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Game Watcher')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.src', '/icon-192.png');

    $response = $this->get('/sw.js')->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
        ->and($response->getContent())->toContain("addEventListener('push'");
});
