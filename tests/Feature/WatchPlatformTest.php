<?php

/**
 * Hlídaná platforma u sledované hry a přidání hry podle odkazu z PS Store (R24).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\StoreListing;
use App\Models\WatchlistItem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
});

/**
 * Připojí ke hře CONTROL Resonant produkt PS Store (PS5 1 419 Kč).
 */
function connectControlResonant(): void
{
    app(ConnectPlayStationProduct::class)->handle(
        Game::query()->where('itad_id', CONTROL_RESONANT_ID)->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    );
}

it('hru jen na PlayStationu bez PS Store přidá a pošle na detail k připojení', function (): void {
    $user = user();

    $this->actingAs($user)
        ->from('/hry/pridat?q=control+resonant')
        ->post('/hry', ['itad_id' => CONTROL_RESONANT_ID, 'platform' => 'playstation'])
        ->assertRedirect('/hry/control-resonant');

    expect(WatchlistItem::query()->sole()->platform)->toBe(Platform::PlayStation);
});

it('hru s připojeným PS Store vrátí zpět na výsledky', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID]);
    connectControlResonant();
    $user->watchlistItems()->delete();

    $this->actingAs($user)
        ->from('/hry/pridat?q=control+resonant')
        ->post('/hry', ['itad_id' => CONTROL_RESONANT_ID, 'platform' => 'playstation'])
        ->assertRedirect('/hry/pridat?q=control+resonant');
});

it('přidání s odkazem z PS Store produkt rovnou připojí', function (): void {
    $user = user();

    $this->actingAs($user)
        ->from('/hry/pridat')
        ->post('/hry', [
            'itad_id' => CONTROL_RESONANT_ID,
            'platform' => 'playstation',
            'listing_url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
        ])
        ->assertRedirect('/hry/pridat');

    expect(StoreListing::query()->sole()->external_id)->toBe(PSN_CONTROL_RESONANT)
        ->and(WatchlistItem::query()->sole()->platform)->toBe(Platform::PlayStation);
});

it('odkaz na cizí web při přidání nevolá a hru pošle na detail s chybou', function (): void {
    $this->actingAs(user())
        ->from('/hry/pridat')
        ->post('/hry', [
            'itad_id' => CONTROL_RESONANT_ID,
            'platform' => 'playstation',
            'listing_url' => 'https://evil.example.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
        ])
        ->assertRedirect('/hry/control-resonant');

    expect(WatchlistItem::query()->count())->toBe(1)
        ->and(StoreListing::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example.com'));
});

it('odmítne neznámou platformu', function (): void {
    $this->actingAs(user())
        ->post('/hry', ['itad_id' => CONTROL_RESONANT_ID, 'platform' => 'xbox'])
        ->assertSessionHasErrors('platform');

    expect(WatchlistItem::query()->count())->toBe(0);
});

it('na přehledu počítá u hry jen na PlayStationu jen ceny PS Store a bez klíčů', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID, BIOMUTANT_ID]);
    connectControlResonant();
    $user->watchlistItems()->whereRelation('game', 'itad_id', CONTROL_RESONANT_ID)->update(['platform' => 'playstation']);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.title', 'BIOMUTANT')
            ->where('games.0.watchPlatform', null)
            ->where('games.1.title', 'CONTROL Resonant')
            ->where('games.1.watchPlatform', 'playstation')
            ->where('games.1.prices.best.platform', 'playstation')
            ->where('games.1.prices.keyshops', null)
            ->where('games.1.needsPlayStation', false));
});

it('hra jen na PlayStationu bez PS Store vyzve k připojení', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);
    $user->watchlistItems()->update(['platform' => 'playstation']);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.prices.best', null)
            ->where('games.0.needsPlayStation', true));
});

it('filtr PC na přehledu hru hlídanou jen na PlayStationu skryje', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID, BIOMUTANT_ID]);
    connectControlResonant();
    $user->watchlistItems()->whereRelation('game', 'itad_id', CONTROL_RESONANT_ID)->update(['platform' => 'playstation']);

    $this->actingAs($user)
        ->get('/?platform=pc')
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 1)
            ->where('games.0.title', 'BIOMUTANT'));
});

it('platformu změní na detailu a prázdná hodnota znamená všude', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);

    $this->actingAs($user)->from('/hry/biomutant')->put('/hry/biomutant/sledovani', ['platform' => 'pc'])->assertRedirect('/hry/biomutant');
    expect(WatchlistItem::query()->sole()->platform)->toBe(Platform::Pc);

    $this->actingAs($user)
        ->get('/hry/biomutant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.watchPlatform', 'pc')
            ->where('game.watchPlatformUrl', '/hry/biomutant/sledovani'));

    $this->actingAs($user)->put('/hry/biomutant/sledovani', ['platform' => null]);
    expect(WatchlistItem::query()->sole()->platform)->toBeNull();
});

it('platformu nesledované hry změnit nejde', function (): void {
    watcherOf([BIOMUTANT_ID]);

    $this->actingAs(user())->put('/hry/biomutant/sledovani', ['platform' => 'pc'])->assertNotFound();
});

it('podle odkazu z PS Store najde hru podle názvu produktu (R24)', function (): void {
    fakeApis(['api.isthereanydeal.com/games/lookup/v1?title=SAROS' => Http::response(jsonFixture('itad/lookup-saros'))]);

    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://store.playstation.com/cs-cz/product/'.PSN_SAROS))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('playstation.name', 'SAROS')
            ->where('playstation.url', 'https://store.playstation.com/cs-cz/product/'.PSN_SAROS)
            ->where('playstationError', null)
            ->where('results.0.itadId', SAROS_ID));
});

it('podle stránky hry na playstation.com najde hru také', function (): void {
    fakeApis(['api.isthereanydeal.com/games/lookup/v1?title=SAROS' => Http::response(jsonFixture('itad/lookup-saros'))]);

    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://www.playstation.com/cs-cz/games/saros/'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('playstation.name', 'SAROS')
            ->where('results.0.itadId', SAROS_ID));
});

it('odkaz z PS Store, ze kterého nejde najít produkt, ukáže hlášku', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://store.playstation.com/cs-cz/product/EP0000-PPSA00000_00-NEEXISTUJE000000'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('playstation', null)
            ->where('playstationError', __('app.listings.not_playstation'))
            ->where('results', []));
});

it('produkt z odkazu podruhé vezme z cache', function (): void {
    $user = user();
    $url = urlencode('https://store.playstation.com/cs-cz/product/'.PSN_SAROS);

    $this->actingAs($user)->get('/hry/pridat?q='.$url);
    $this->actingAs($user)->get('/hry/pridat?q='.$url);

    // Kurzy ČNB (beforeEach), stránka produktu, search, lookup názvu, ceny
    // a stránky dvou her na playstation.com — podruhé nic
    Http::assertSentCount(7);
});

it('stránka hry bez odkazu do obchodu najde produkt přes koncept v meta značce', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q='.urlencode('https://www.playstation.com/cs-cz/games/until-dawn/'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('playstation.name', 'Until Dawn™')
            ->where('playstation.url', 'https://store.playstation.com/cs-cz/product/'.PSN_UNTIL_DAWN)
            ->where('playstationError', null));

    // V katalogu se hledá bez ochranné známky
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/lookup/v1?title=Until%20Dawn')
        && ! str_contains(urldecode($request->url()), '™'));
});

it('hru se stránkou na playstation.com označí ve výsledcích jako PlayStation', function (): void {
    fakeApis(['www.playstation.com/cs-cz/games/control-resonant/' => Http::response(psnFixture('game-page-until-dawn'))]);

    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.onPlayStation', true)
            ->where('results.0.offerPlayStation', true)
            ->where('results.0.playstationUrl', 'https://www.playstation.com/cs-cz/games/control-resonant/')
            // DLC se na playstation.com neověřuje
            ->where('results.2.type', 'dlc')
            ->where('results.2.playstationUrl', null));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD'
        && $request->url() === 'https://www.playstation.com/cs-cz/games/control-resonant/');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'digital-deluxe-upgrade'));
});

it('hru bez stránky na playstation.com a s obchody pro PC jako PlayStation neoznačí', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.onPlayStation', false)
            ->where('results.0.offerPlayStation', false)
            ->where('results.0.playstationUrl', null));
});

it('výpadek playstation.com hledání nezastaví a neuloží se do cache', function (): void {
    fakeApis(['www.playstation.com/*' => Http::response('', 503)]);
    $user = user();

    $this->actingAs($user)
        ->get('/hry/pridat?q=control+resonant')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('results.0.onPlayStation', false));

    fakeApis(['www.playstation.com/cs-cz/games/control-resonant/' => Http::response(psnFixture('game-page-until-dawn'))]);
    $this->actingAs($user)
        ->get('/hry/pridat?q=control+resonant')
        ->assertInertia(fn (Assert $page) => $page->where('results.0.onPlayStation', true));
});

it('„Jen na PS“ se stránkou hry na playstation.com připojí produkt rovnou', function (): void {
    $this->actingAs(user())
        ->from('/hry/pridat?q=saros')
        ->post('/hry', [
            'itad_id' => SAROS_ID,
            'platform' => 'playstation',
            'listing_url' => 'https://www.playstation.com/cs-cz/games/saros/',
        ])
        ->assertRedirect('/hry/pridat?q=saros');

    expect(StoreListing::query()->sole()->external_id)->toBe(PSN_SAROS)
        ->and(WatchlistItem::query()->sole()->platform)->toBe(Platform::PlayStation);
});
