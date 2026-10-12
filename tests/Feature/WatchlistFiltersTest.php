<?php

/**
 * Pokročilé filtry přehledu sledovaných her (R21).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\RefreshGameDetails;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    // Ceny po převodu: BIOMUTANT 608 Kč (−30 %), CONTROL Resonant 1 198 Kč (−18 %)
    // a PS5 1 419 Kč, Alan Wake 2 1 218 Kč (bez slevy, bez Steamu)
    $this->user = watcherOf([BIOMUTANT_ID, CONTROL_RESONANT_ID, ALAN_WAKE_2_ID]);
    app(ConnectPlayStationProduct::class)->handle(
        Game::query()->where('itad_id', CONTROL_RESONANT_ID)->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    );
});

/**
 * Názvy her na přehledu v pořadí, jak je stránka dostala.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function dashboardTitles(array $query = []): array
{
    $response = test()->actingAs(test()->user)->get('/?'.http_build_query($query))->assertOk();

    return array_column($response->viewData('page')['props']['games'], 'title');
}

it('bez filtrů ukáže všechny hry a žánry s počtem her', function (): void {
    // Žánry ze Steamu: BIOMUTANT Akční, RPG; CONTROL Resonant Akční, Dobrodružné, RPG;
    // Alan Wake 2 na Steamu není
    $this->actingAs($this->user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 3)
            ->where('total', 3)
            ->where('filters.active', false)
            ->where('filters.sort', 'added')
            ->where('genres', [
                ['name' => 'Akční', 'count' => 2],
                ['name' => 'RPG', 'count' => 2],
                ['name' => 'Dobrodružné', 'count' => 1],
            ]));
});

it('hledá podle názvu bez ohledu na velikost písmen', function (): void {
    expect(dashboardTitles(['q' => 'resonant']))->toBe(['CONTROL Resonant']);
});

it('platforma PlayStation ukáže jen hry s cenou v PS Store a nejlevněji podle ní', function (): void {
    $this->actingAs($this->user)
        ->get('/?platform=playstation')
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 1)
            ->where('games.0.prices.best.shop', 'PlayStation Store')
            ->where('games.0.prices.best.price', ['amount' => 1419, 'currency' => 'CZK'])
            // Obchody s klíči prodávají klíče pro PC
            ->where('games.0.prices.keyshops', null)
            ->where('filters.platform', 'playstation')
            ->where('filters.active', true));
});

it('bez filtru platformy je PlayStation na kartě zvlášť vedle nejlevnějšího PC', function (): void {
    $this->actingAs($this->user)
        ->get('/?q=control')
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.prices.best.shop', 'AllYouPlay')
            ->where('games.0.prices.playstation.shop', 'PlayStation Store'));
});

it('filtruje podle žánru — stačí jeden z vybraných (R25)', function (array $genres, array $titles): void {
    expect(dashboardTitles(['genres' => $genres, 'sort' => 'title']))->toBe($titles);
})->with([
    'Dobrodružné' => [['Dobrodružné'], ['CONTROL Resonant']],
    'RPG' => [['RPG'], ['BIOMUTANT', 'CONTROL Resonant']],
    'Závodní' => [['Závodní'], []],
]);

it('filtruje podle počtu hráčů — hra bez údajů vypadne (R25)', function (): void {
    $itTakesTwo = Game::factory()->create(['title' => 'It Takes Two', 'steam_app_id' => IT_TAKES_TWO_STEAM_ID]);
    $this->user->watchlistItems()->create(['game_id' => $itTakesTwo->id]);
    app(RefreshGameDetails::class)->handle(collect([$itTakesTwo]));

    expect(dashboardTitles(['players' => 'solo', 'sort' => 'title']))->toBe(['BIOMUTANT', 'CONTROL Resonant'])
        ->and(dashboardTitles(['players' => 'duo']))->toBe(['It Takes Two'])
        ->and(dashboardTitles(['players' => 'couch']))->toBe(['It Takes Two']);
});

it('couch co-op pozná i z počtu hráčů v PS Store', function (): void {
    $game = Game::factory()->create(['title' => 'It Takes Two']);
    $this->user->watchlistItems()->create(['game_id' => $game->id]);
    app(ConnectPlayStationProduct::class)->handle($game, 'https://store.playstation.com/cs-cz/product/'.PSN_IT_TAKES_TWO);

    expect(dashboardTitles(['players' => 'couch']))->toBe(['It Takes Two']);

    $this->actingAs($this->user)
        ->get('/hry/'.$game->slug)
        ->assertInertia(fn (Assert $page) => $page->where('game.modes', ['multi', 'local_multi']));
});

it('jen ve slevě, sleva aspoň a cena do', function (array $query, array $titles): void {
    expect(dashboardTitles([...$query, 'sort' => 'title']))->toBe($titles);
})->with([
    'jen ve slevě' => [['deals' => 1], ['BIOMUTANT', 'CONTROL Resonant']],
    'sleva aspoň 25 %' => [['min_cut' => 25], ['BIOMUTANT']],
    'cena do 1 200 Kč' => [['max_price' => 1200], ['BIOMUTANT', 'CONTROL Resonant']],
    'cena do 700 Kč' => [['max_price' => 700], ['BIOMUTANT']],
    'jen DLC' => [['type' => 'dlc'], []],
]);

it('řadí podle názvu, ceny a slevy', function (string $sort, array $titles): void {
    expect(dashboardTitles(['sort' => $sort]))->toBe($titles);
})->with([
    'podle názvu' => ['title', ['Alan Wake 2', 'BIOMUTANT', 'CONTROL Resonant']],
    'od nejlevnější' => ['price', ['BIOMUTANT', 'CONTROL Resonant', 'Alan Wake 2']],
    'od největší slevy' => ['cut', ['BIOMUTANT', 'CONTROL Resonant', 'Alan Wake 2']],
]);

it('s PS Plus počítá u PlayStation Store cenu pro předplatitele', function (): void {
    fakeApis(['store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US => Http::response(psnFixture('product-kcd2-expansion-us'))]);
    app(ConnectPlayStationProduct::class)->handle(
        Game::query()->where('itad_id', BIOMUTANT_ID)->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US,
    );

    $withoutPlus = $this->actingAs($this->user)->get('/?platform=playstation&sort=price')->viewData('page')['props']['games'];
    $withPlus = $this->actingAs($this->user)->get('/?platform=playstation&sort=price&plus=1')->viewData('page')['props']['games'];

    $kcd = fn (array $games): array => collect($games)->firstWhere('title', 'BIOMUTANT')['prices']['best'];
    expect($kcd($withPlus)['effectiveCents'])->toBeLessThan($kcd($withoutPlus)['effectiveCents'])
        ->and($kcd($withPlus)['member'])->not->toBeNull();
});

it('neplatné hodnoty filtrů ignoruje a přehled ukáže', function (): void {
    $this->actingAs($this->user)
        ->get('/?platform=xbox&sort=nahodne&min_cut=500&max_price=-1&genres=akcni&players=10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 3)
            ->where('filters.platform', null)
            ->where('filters.sort', 'added')
            ->where('filters.active', false));
});
