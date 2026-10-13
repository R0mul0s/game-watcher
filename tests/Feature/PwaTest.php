<?php

/**
 * Aplikace na ploše — manifest, service worker, stránka offline a sdílení odkazu (R37).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('manifest má zkratky, maskovatelnou ikonu, launch_handler a sdílení do hledání', function (): void {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('id', '/')
        ->assertJsonCount(3, 'shortcuts')
        ->assertJsonPath('shortcuts.1.url', '/hry/pridat')
        ->assertJsonPath('shortcuts.1.name', 'Přidat hru')
        ->assertJsonPath('icons.2.purpose', 'maskable')
        ->assertJsonPath('icons.2.src', '/icon-maskable-512.png')
        ->assertJsonPath('launch_handler.client_mode', ['navigate-existing', 'auto'])
        ->assertJsonPath('share_target.action', '/hry/pridat')
        ->assertJsonPath('share_target.method', 'GET');

    expect(public_path('icon-maskable-512.png'))->toBeFile();
});

it('service worker dostane verzi a soubory stránky offline', function (): void {
    $script = (string) $this->get('/sw.js')->assertOk()->assertHeader('Cache-Control', 'no-cache, private')->getContent();

    expect($script)->toStartWith('self.SW_CONFIG = {')
        ->and($script)->toContain('"offlineUrl":"/offline"')
        ->and($script)->toContain("addEventListener('fetch'")
        ->and($script)->toContain('setAppBadge');
});

it('stránka offline je bez přihlášení a bez dat účtu', function (): void {
    $this->get('/offline')
        ->assertOk()
        ->assertSeeText(__('app.ui.pwa.offline_title'))
        ->assertSeeText(__('app.ui.pwa.offline_retry'))
        ->assertDontSee('data-page', false);
});

it('odkaz sdílený z jiné aplikace v textu hledá jako odkaz (share_target)', function (array $shared): void {
    $this->actingAs(user())
        ->get('/hry/pridat?'.http_build_query($shared))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('query', 'https://store.steampowered.com/app/597820/BIOMUTANT/')
            ->where('searched', true)
            ->where('results.0.itadId', BIOMUTANT_ID));
})->with([
    'odkaz v textu s názvem' => [['title' => 'BIOMUTANT', 'text' => 'BIOMUTANT na Steamu https://store.steampowered.com/app/597820/BIOMUTANT/']],
    'odkaz v url' => [['url' => 'https://store.steampowered.com/app/597820/BIOMUTANT/', 'text' => 'Koukni na tohle']],
]);

it('stránky dostanou nastavení aplikace na ploše', function (): void {
    $this->actingAs(user())
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('pwa.refreshAfterMinutes', (int) config('games.pwa.refresh_after_minutes')));
});
