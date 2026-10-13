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
            ->where('filters.sort', 'price')
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

it('filtruje podle češtiny ze Steamu i z PS Store — neznámá vypadne (R47)', function (): void {
    // BIOMUTANT a CONTROL Resonant češtinu nemají, Alan Wake 2 ji nezná (není na Steamu)
    $cyberpunk = Game::factory()->create(['title' => 'Cyberpunk 2077', 'steam_app_id' => CYBERPUNK_STEAM_ID]);
    $kingdomCome = Game::factory()->create(['title' => 'Kingdom Come']);
    $this->user->watchlistItems()->create(['game_id' => $cyberpunk->id]);
    $this->user->watchlistItems()->create(['game_id' => $kingdomCome->id]);
    app(RefreshGameDetails::class)->handle(collect([$cyberpunk]));
    app(ConnectPlayStationProduct::class)->handle($kingdomCome, 'https://store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME);

    expect(dashboardTitles(['czech' => 'text', 'sort' => 'title']))->toBe(['Cyberpunk 2077', 'Kingdom Come'])
        ->and(dashboardTitles(['czech' => 'voice']))->toBe(['Kingdom Come']);

    $cards = collect($this->actingAs($this->user)->get('/')->viewData('page')['props']['games'])->pluck('czech', 'title');
    expect($cards->all())->toMatchArray([
        'Cyberpunk 2077' => 'text',
        'Kingdom Come' => 'voice',
        'BIOMUTANT' => 'none',
        'Alan Wake 2' => null,
    ]);
});

it('na detailu ukáže češtinu po platformách, jen když se liší (R47)', function (): void {
    $game = Game::factory()->create(['title' => 'Kingdom Come', 'steam_app_id' => CYBERPUNK_STEAM_ID]);
    $this->user->watchlistItems()->create(['game_id' => $game->id]);
    app(RefreshGameDetails::class)->handle(collect([$game]));

    $this->actingAs($this->user)
        ->get('/hry/'.$game->slug)
        ->assertInertia(fn (Assert $page) => $page->where('game.czech', [['platform' => null, 'support' => 'text']]));

    app(ConnectPlayStationProduct::class)->handle($game, 'https://store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME);

    $this->actingAs($this->user)
        ->get('/hry/'.$game->slug)
        ->assertInertia(fn (Assert $page) => $page->where('game.czech', [
            ['platform' => 'pc', 'support' => 'text'],
            ['platform' => 'playstation', 'support' => 'voice'],
        ]));
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
        ->get('/?platform=xbox&sort=nahodne&min_cut=500&max_price=-1&genres=akcni&players=10&czech=none')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 3)
            ->where('filters.platform', null)
            ->where('filters.sort', 'price')
            ->where('filters.active', false));
});

it('hledá i ve vývojáři, vydavateli a žánru, bez diakritiky a po slovech (R30)', function (string $query, array $titles): void {
    expect(dashboardTitles(['q' => $query, 'sort' => 'title']))->toBe($titles);
})->with([
    'vývojář' => ['remedy', ['Alan Wake 2', 'CONTROL Resonant']],
    'vydavatel' => ['THQ', ['BIOMUTANT']],
    'žánr bez diakritiky' => ['dobrodruzne', ['CONTROL Resonant']],
    'každé slovo někde' => ['remedy rpg', ['CONTROL Resonant']],
]);

it('cena podle obchodů s klíči řadí i filtruje „cena do“ (R30)', function (string $basis, array $titles): void {
    // Klíče: BIOMUTANT 5,41 € (132 Kč), CONTROL Resonant 38,98 € (950 Kč), Alan Wake 2 bez klíčů
    expect(dashboardTitles(['price' => $basis, 'max_price' => 1000, 'sort' => 'price']))->toBe($titles);
})->with([
    'oficiální' => ['official', ['BIOMUTANT']],
    'včetně klíčů' => ['cheapest', ['BIOMUTANT', 'CONTROL Resonant']],
    'jen klíče' => ['keyshops', ['BIOMUTANT', 'CONTROL Resonant']],
]);

it('s cenou podle klíčů je hra bez klíčů při řazení na konci a karta ukáže klíče jako hlavní cenu', function (): void {
    expect(dashboardTitles(['price' => 'keyshops', 'sort' => 'price']))->toBe(['BIOMUTANT', 'CONTROL Resonant', 'Alan Wake 2']);

    $this->actingAs($this->user)
        ->get('/?price=keyshops&q=biomutant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.price', 'keyshops')
            ->where('filters.active', true)
            ->where('games.0.ranked.keyshops', true)
            ->where('games.0.ranked.shopKey', 'keyshops')
            // Oficiální nabídka zůstává pro vedlejší řádek karty
            ->where('games.0.prices.best.cut', 30));
});

it('bez volby řadí od nejlevnější (R32)', function (): void {
    expect(dashboardTitles())->toBe(['BIOMUTANT', 'CONTROL Resonant', 'Alan Wake 2']);
});

it('řadí podle Metacritic, hra bez hodnocení na konci, a karta hodnocení ukáže (R32)', function (): void {
    // Metascore: CONTROL Resonant 83, BIOMUTANT 66, Alan Wake 2 jen OpenCritic
    expect(dashboardTitles(['sort' => 'score']))->toBe(['CONTROL Resonant', 'BIOMUTANT', 'Alan Wake 2']);

    $games = collect($this->actingAs($this->user)->get('/')->viewData('page')['props']['games'])->keyBy('title');
    expect($games['CONTROL Resonant']['metascore'])->toBe(['score' => 83, 'level' => 'good'])
        ->and($games['BIOMUTANT']['metascore'])->toBe(['score' => 66, 'level' => 'mixed'])
        ->and($games['Alan Wake 2']['metascore'])->toBeNull();
});

it('sleva se i s cenou podle klíčů počítá z oficiálních obchodů', function (): void {
    expect(dashboardTitles(['price' => 'keyshops', 'min_cut' => 25, 'sort' => 'title']))->toBe(['BIOMUTANT']);
});
