<?php

/**
 * Seznam sledovaných her a detail hry (etapa 2, R10, R11).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Models\Game;
use App\Models\WatchlistItem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('přidá novou hru — převezme ji z ITAD do katalogu a dá na seznam', function (): void {
    $user = user();

    $this->actingAs($user)
        ->from('/hry/pridat?q=control')
        ->post('/hry', ['itad_id' => CONTROL_RESONANT_ID])
        ->assertRedirect('/hry/pridat?q=control')
        ->assertInertiaFlash('success', __('app.watchlist.added', ['title' => 'CONTROL Resonant']));

    $game = Game::query()->sole();
    expect($game->itad_id)->toBe(CONTROL_RESONANT_ID)
        ->and($game->slug)->toBe('control-resonant')
        ->and($game->title)->toBe('CONTROL Resonant')
        ->and($game->steam_app_id)->toBe(3669870)
        ->and($game->release_date?->toDateString())->toBe('2026-09-24')
        ->and($game->developers)->toBe(['Remedy Entertainment'])
        ->and($game->reviews)->toHaveCount(4)
        ->and($game->boxart_url)->toContain('/boxart.jpg')
        ->and($game->banner_url)->toContain('/banner600.jpg')
        ->and($user->isWatching($game))->toBeTrue();
});

it('hru, kterou katalog má, už z ITAD nestahuje', function (): void {
    Game::factory()->create(['itad_id' => BIOMUTANT_ID]);

    $this->actingAs(user())->post('/hry', ['itad_id' => BIOMUTANT_ID]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/games/info/'));
    expect(WatchlistItem::query()->count())->toBe(1);
});

it('nové hře rovnou stáhne ceny, hře s cenami už ne', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();

    expect($game->currentPrices()->count())->toBe(16);

    fakeApis();
    $this->actingAs(user())->post('/hry', ['itad_id' => BIOMUTANT_ID]);

    Http::assertNothingSent();
});

it('výpadek zdroje cen přidání hry nezastaví', function (): void {
    fakeApis([
        'api.isthereanydeal.com/games/prices/*' => apiError(503),
        'api.gg.deals/*' => apiError(503),
    ]);

    $this->actingAs(user())
        ->post('/hry', ['itad_id' => BIOMUTANT_ID])
        ->assertInertiaFlash('success', __('app.watchlist.added', ['title' => 'BIOMUTANT']));

    expect(WatchlistItem::query()->count())->toBe(1);
});

it('opakované přidání nic nezdvojí', function (): void {
    $user = user();

    $this->actingAs($user)->post('/hry', ['itad_id' => BIOMUTANT_ID]);
    $this->actingAs($user)->post('/hry', ['itad_id' => BIOMUTANT_ID]);

    expect(Game::query()->count())->toBe(1)
        ->and($user->watchlistItems()->count())->toBe(1);
});

it('hru, kterou ITAD nezná, nepřidá', function (): void {
    $this->actingAs(user())
        ->from('/hry/pridat')
        ->post('/hry', ['itad_id' => '00000000-0000-0000-0000-000000000000'])
        ->assertRedirect('/hry/pridat')
        ->assertInertiaFlash('error', __('app.catalog.game_not_found'));

    expect(Game::query()->count())->toBe(0);
});

it('odmítne ITAD ID v nesprávném tvaru', function (): void {
    $this->actingAs(user())
        ->post('/hry', ['itad_id' => '../../games'])
        ->assertSessionHasErrors('itad_id');

    Http::assertNothingSent();
});

it('výpadek ITAD při přidání ohlásí a nic neuloží', function (): void {
    fakeApis(['api.isthereanydeal.com/*' => apiError(403, 'Invalid or expired api key')]);

    $this->actingAs(user())
        ->post('/hry', ['itad_id' => CONTROL_RESONANT_ID])
        ->assertInertiaFlash('error', __('app.catalog.unavailable'));

    expect(Game::query()->count())->toBe(0);
});

it('odebrání ze seznamu nechá hru v katalogu', function (): void {
    $user = user();
    $game = Game::factory()->create();
    $user->watchlistItems()->create(['game_id' => $game->id]);

    $this->actingAs($user)
        ->from("/hry/{$game->slug}")
        ->delete("/hry/{$game->slug}/sledovani")
        ->assertRedirect("/hry/{$game->slug}")
        ->assertInertiaFlash('success', __('app.watchlist.removed', ['title' => $game->title]));

    expect($user->isWatching($game))->toBeFalse()
        ->and(Game::query()->count())->toBe(1);
});

it('odebrání nesáhne na seznam jiného účtu', function (): void {
    $other = user();
    $game = Game::factory()->create();
    $other->watchlistItems()->create(['game_id' => $game->id]);

    $this->actingAs(user())->delete("/hry/{$game->slug}/sledovani");

    expect($other->isWatching($game))->toBeTrue();
});

it('přehled ukáže jen vlastní hry, naposledy přidané první', function (): void {
    $user = user();
    [$older, $newer, $foreign] = Game::factory()->count(3)->create();
    $user->watchlistItems()->create(['game_id' => $older->id]);
    $this->travel(1)->minutes();
    $user->watchlistItems()->create(['game_id' => $newer->id]);
    user()->watchlistItems()->create(['game_id' => $foreign->id]);

    // Výchozí řazení je od nejlevnější (R32) — pořadí přidání jen na vyžádání
    $this->actingAs($user)
        ->get('/?sort=added')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('games', 2)
            ->where('games.0.slug', $newer->slug)
            ->where('games.0.url', "/hry/{$newer->slug}")
            ->where('games.1.slug', $older->slug));
});

it('detail ukáže údaje hry, hodnocení s úrovní a odkaz na Steam', function (): void {
    $user = user();
    $this->actingAs($user)->post('/hry', ['itad_id' => CONTROL_RESONANT_ID]);

    $this->actingAs($user)
        ->get('/hry/control-resonant')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->where('game.title', 'CONTROL Resonant')
            ->where('game.releaseDate', '2026-09-24')
            ->where('game.watching', true)
            ->where('game.steamUrl', 'https://store.steampowered.com/app/3669870/')
            ->where('game.unwatchUrl', '/hry/control-resonant/sledovani')
            ->where('game.reviews.0.score', 90)
            ->where('game.reviews.0.level', 'good'));
});

it('detail hry mimo Steam nemá odkaz na Steam a nesledovanou hru tak ukáže', function (): void {
    $this->actingAs(user())->post('/hry', ['itad_id' => ALAN_WAKE_2_ID]);
    $game = Game::query()->sole();
    $game->watchlistItems()->delete();

    $this->actingAs(user())
        ->get("/hry/{$game->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.steamUrl', null)
            ->where('game.watching', false));
});

it('neznámá hra je 404', function (): void {
    $this->actingAs(user())->get('/hry/neexistuje')->assertNotFound();
});
