<?php

/**
 * Hledání hry k přidání (etapa 2, R10).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
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

    // Hledání podle názvu = search + lookup přesného názvu (R22) + ceny
    // pro obchody (R23) + stránky dvou her na playstation.com (R24); podruhé nic
    Http::assertSentCount(5);
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

it('u výsledků ukáže obchody — hlavní napřed — a nejnižší cenu v měně účtu (R23)', function (): void {
    app(ImportExchangeRates::class)->handle();

    // CONTROL Resonant: 12 obchodů, nejlevněji AllYouPlay 49,18 €
    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.availabilityKnown', true)
            ->where('results.0.shops', ['Steam', 'Epic Game Store', 'AllYouPlay', 'DLGamer'])
            ->where('results.0.moreShops', 8)
            ->where('results.0.bestPrice.currency', 'CZK')
            ->where('results.0.free', false)
            ->where('results.0.onPlayStation', false)
            // Hra jen v obchodech pro PC — „Jen na PS“ se nenabízí (R24)
            ->where('results.0.offerPlayStation', false)
            // Edice bez nabídky v obchodech
            ->where('results.1.shops', [])
            ->where('results.1.bestPrice', null)
            ->where('results.1.offerPlayStation', true));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/prices/v3')
        && in_array(CONTROL_RESONANT_ID, $request->data(), true));
});

it('cenu v měně zdroje převádět nemusí', function (): void {
    $this->actingAs(user(['display_currency' => 'EUR']))
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.bestPrice', ['amount' => 49.18, 'currency' => 'EUR']));
});

it('hru s připojeným PlayStation Store označí, i když se na PC neprodává', function (): void {
    $user = watcherOf([SAROS_ID]);
    app(ConnectPlayStationProduct::class)->handle(
        Game::query()->where('itad_id', SAROS_ID)->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_SAROS,
    );

    $this->actingAs($user)
        ->get('/hry/pridat?q=Saros')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.itadId', SAROS_ID)
            ->where('results.0.onPlayStation', true)
            ->where('results.0.offerPlayStation', true)
            ->where('results.0.shops', [])
            ->where('results.0.bestPrice', null));
});

it('výsledky filtruje podle druhu a platformy, stav je v adrese (R30)', function (string $query, array $titles): void {
    $props = $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant&'.$query)
        ->assertOk()
        ->viewData('page')['props'];

    // „found“ je počet před filtry — stránka pozná „nic jsme nenašli“ od „nic filtrům“
    expect($props['found'])->toBe(3)
        ->and(array_column($props['results'], 'title'))->toBe($titles);
})->with([
    'jen hry (i edice)' => ['type=game', ['CONTROL Resonant', 'CONTROL Resonant Digital Deluxe Edition']],
    'DLC a další' => ['type=other', ['CONTROL Resonant Digital Deluxe Upgrade']],
    'v obchodech pro PC' => ['platform=pc', ['CONTROL Resonant']],
    'na PlayStationu nic' => ['platform=playstation', []],
]);

it('změna filtru vezme hledání i obchody z cache', function (): void {
    $user = user();
    $this->actingAs($user)->get('/hry/pridat?q=control+resonant')->assertOk();
    $sent = count(Http::recorded());

    $this->actingAs($user)->get('/hry/pridat?q=control+resonant&type=game&platform=pc&sort=price')->assertOk();

    expect(count(Http::recorded()))->toBe($sent);
});

it('řadí výsledky od nejlevnější, hry bez ceny na konci', function (): void {
    app(ImportExchangeRates::class)->handle();
    // Skutečné objekty z ITAD: BIOMUTANT (od 24,96 €) za výsledky CONTROL Resonant (od 49,18 €)
    fakeApis(['api.isthereanydeal.com/games/search/v1*' => Http::response([
        ...jsonFixture('itad/search-control-resonant'),
        jsonFixture('itad/lookup-biomutant')['game'],
    ])]);

    $titles = fn (string $sort): array => array_column(
        $this->actingAs(user())->get('/hry/pridat?q=control&sort='.$sort)->viewData('page')['props']['results'],
        'title',
    );

    expect($titles('relevance'))->toBe(['CONTROL Resonant', 'CONTROL Resonant Digital Deluxe Edition', 'CONTROL Resonant Digital Deluxe Upgrade', 'BIOMUTANT'])
        ->and($titles('price'))->toBe(['BIOMUTANT', 'CONTROL Resonant', 'CONTROL Resonant Digital Deluxe Edition', 'CONTROL Resonant Digital Deluxe Upgrade']);
});

it('„Zobrazit víc“ si od ITAD řekne o víc výsledků', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant&more=1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.more', true)
            ->where('canShowMore', false));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/search/v1')
        && (int) $request['results'] === (int) config('games.catalog.search_results_more'));
});

it('plný počet výsledků nabídne víc', function (): void {
    config(['games.catalog.search_results' => 3]);

    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page->where('canShowMore', true));
});

it('podle odkazu na IsThereAnyDeal najde přesně tu hru (R30)', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://isthereanydeal.com/game/control-resonant/info/'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('results', 1)
            ->where('results.0.itadId', CONTROL_RESONANT_ID));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/search/v1')
        && $request['title'] === 'control resonant');
});

it('neplatné filtry hledání ignoruje', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant&type=hry&platform=xbox&sort=nahodne&more=snad')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('results', 3)
            ->where('filters', ['type' => null, 'platform' => null, 'sort' => 'relevance', 'more' => false]));
});

it('výpadek cen ITAD výsledky neskryje, jen bez obchodů', function (): void {
    fakeApis(['api.isthereanydeal.com/games/prices/v3*' => apiError(503)]);

    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('unavailable', false)
            ->has('results', 3)
            ->where('results.0.availabilityKnown', false)
            ->where('results.0.shops', []));
});
