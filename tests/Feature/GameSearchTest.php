<?php

/**
 * Hledání hry k přidání (etapa 2, R10).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('vyžaduje přihlášení', function (): void {
    $this->get('/hry/pridat?q=control')->assertRedirect('/prihlaseni');
});

it('bez dotazu ukáže jen formulář a ITAD nevolá', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Search')
            ->where('searched', false)
            ->where('results', []));

    Http::assertNothingSent();
});

it('najde hry podle názvu a klíč pošle v hlavičce, ne v adrese', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('searched', true)
            ->where('unavailable', false)
            ->has('results', 3)
            ->where('results.0.itadId', CONTROL_RESONANT_ID)
            ->where('results.0.title', 'CONTROL Resonant')
            ->where('results.0.watching', false)
            ->where('results.0.detailUrl', null)
            ->where('results.2.type', 'dlc'));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/search/v1')
        && $request['title'] === 'control resonant'
        && $request->hasHeader('ITAD-API-Key', 'test-itad-key')
        && ! str_contains($request->url(), 'test-itad-key'));
});

it('podle odkazu na Steam hledá přesně přes Steam App ID', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://store.steampowered.com/app/597820/BIOMUTANT/'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('results', 1)
            ->where('results.0.itadId', BIOMUTANT_ID));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/lookup/v1?appid=597820'));
});

it('neznámé Steam App ID nic nenajde', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=999999999')
        ->assertInertia(fn (Assert $page) => $page
            ->where('searched', true)
            ->where('results', []));
});

it('u výsledků vyznačí hry z katalogu a ty, které uživatel sleduje', function (): void {
    $user = user();
    $game = Game::factory()->create(['itad_id' => CONTROL_RESONANT_ID, 'slug' => 'control-resonant']);
    $user->watchlistItems()->create(['game_id' => $game->id]);

    $this->actingAs($user)
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.watching', true)
            ->where('results.0.detailUrl', '/hry/control-resonant')
            ->where('results.1.watching', false));
});

it('stejné hledání podruhé vezme z cache', function (): void {
    $user = user();

    $this->actingAs($user)->get('/hry/pridat?q=Control+Resonant');
    $this->actingAs($user)->get('/hry/pridat?q=control+resonant');

    Http::assertSentCount(1);
});

it('výpadek ITAD ukáže hlášku místo chyby stránky', function (): void {
    fakeApis(['api.isthereanydeal.com/*' => apiError(503, 'Service Unavailable')]);

    $this->actingAs(user())
        ->get('/hry/pridat?q=control')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unavailable', true)
            ->where('results', []));
});

it('odmítne příliš dlouhý dotaz', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q='.str_repeat('a', (int) config('games.catalog.max_query_length') + 1))
        ->assertSessionHasErrors('q');

    Http::assertNothingSent();
});

it('omezí počet hledání za minutu', function (): void {
    $user = user();
    $limit = (int) config('games.catalog.max_searches_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->get('/hry/pridat?q=control')->assertOk();
    }

    $this->actingAs($user)->get('/hry/pridat?q=control')->assertTooManyRequests();
});
