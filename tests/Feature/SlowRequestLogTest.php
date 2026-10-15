<?php

/**
 * Log pomalého hledání hry s časy volání zdrojů (R56).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('pomalé hledání zapíše do logu volání zdrojů bez klíčů z adresy', function (): void {
    config(['games.monitoring.slow_request_seconds' => 0]);
    Log::spy();

    $this->actingAs(user())->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->has('playstationPages')));

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
        $calls = implode("\n", $context['calls']);

        return str_starts_with($message, 'Pomalé hledání „control resonant“: ')
            && str_contains($calls, 'GET api.isthereanydeal.com/games/search/v1 200')
            // Klíč ITAD je v dotazu adresy — do logu nesmí
            && ! str_contains($calls, 'key=');
    })->atLeast()->once();
    // Stránky na playstation.com se měří zvlášť v odloženém požadavku (R56)
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_starts_with($message, 'Pomalé ověření PlayStationu pro „control resonant“: ')
        && str_contains(implode("\n", $context['calls']), 'HEAD www.playstation.com/cs-cz/games/control-resonant/ 404'))->once();
});

it('rychlé hledání do logu nic nezapíše', function (): void {
    Log::spy();

    $this->actingAs(user())->get('/hry/pridat?q=control+resonant')->assertOk();

    Log::shouldNotHaveReceived('warning');
});
