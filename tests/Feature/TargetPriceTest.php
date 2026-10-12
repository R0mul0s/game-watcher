<?php

/**
 * Cílová cena sledované hry a slevy dne na přehledu (etapa 6, R26).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    // BIOMUTANT: nejlevněji klíče 131,81 Kč, oficiálně WinGameStore 608 Kč
    $this->user = watcherOf([BIOMUTANT_ID]);
});

it('uloží cílovou cenu v měně účtu a smaže stav posledního upozornění', function (): void {
    WatchlistItem::query()->update(['notified_amount' => '131.81', 'notified_currency' => 'CZK']);

    $this->actingAs($this->user)
        ->from('/hry/biomutant')
        ->put('/hry/biomutant/cil', ['target_price' => '499.5'])
        ->assertRedirect('/hry/biomutant');

    $item = WatchlistItem::query()->sole();
    expect($item->target_amount)->toBe('499.50')
        ->and($item->target_currency)->toBe('CZK')
        ->and($item->notified_amount)->toBeNull();
});

it('prázdná cílová cena cíl zruší', function (): void {
    WatchlistItem::query()->update(['target_amount' => '500.00', 'target_currency' => 'CZK']);

    $this->actingAs($this->user)->put('/hry/biomutant/cil', ['target_price' => '']);

    expect(WatchlistItem::query()->sole()->target_amount)->toBeNull();
});

it('odmítne nesmyslnou cílovou cenu', function (string $value): void {
    $this->actingAs($this->user)
        ->put('/hry/biomutant/cil', ['target_price' => $value])
        ->assertSessionHasErrors('target_price');

    expect(WatchlistItem::query()->sole()->target_amount)->toBeNull();
})->with(['1e3', '-5', '0', '12.345', 'abc', '100000000']);

it('cílovou cenu nesledované hry nastavit nejde', function (): void {
    $this->actingAs(user())->put('/hry/biomutant/cil', ['target_price' => '100'])->assertNotFound();
});

it('karta i detail ukážou cíl, karta i to, že je splněný', function (): void {
    WatchlistItem::query()->update(['target_amount' => '200.00', 'target_currency' => 'CZK']);

    $this->actingAs($this->user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.target', ['amount' => 200, 'currency' => 'CZK'])
            ->where('games.0.targetReached', true));

    $this->actingAs($this->user)
        ->get('/hry/biomutant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.target', ['amount' => 200, 'currency' => 'CZK'])
            ->where('game.targetUrl', '/hry/biomutant/cil'));

    WatchlistItem::query()->update(['target_amount' => '100.00']);
    $this->actingAs($this->user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('games.0.targetReached', false));
});

it('slevy dne ukážou hru, která zlevnila od včerejška', function (): void {
    $yesterday = CarbonImmutable::now((string) config('games.display_timezone'))->subDay()->toDateString();
    $gameId = WatchlistItem::query()->sole()->game_id;
    // Včera nejlevněji klíče za 6,00 € (146,19 Kč), dnes 5,41 € (131,81 Kč)
    DB::table('price_history')->insert(['game_id' => $gameId, 'shop_key' => 'keyshops', 'day' => $yesterday, 'amount' => '6.00', 'currency' => 'EUR']);

    $this->actingAs($this->user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('dailyDeals', 1)
            ->where('dailyDeals.0.title', 'BIOMUTANT')
            ->where('dailyDeals.0.price', ['amount' => 131.81, 'currency' => 'CZK'])
            ->where('dailyDeals.0.previous', ['amount' => 146.19, 'currency' => 'CZK'])
            ->where('dailyDeals.0.drop', 10));
});

it('slevy dne vynechají hru, která nezlevnila nebo nemá včerejší cenu', function (): void {
    $yesterday = CarbonImmutable::now((string) config('games.display_timezone'))->subDay()->toDateString();

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page->where('dailyDeals', []));

    DB::table('price_history')->insert(['game_id' => WatchlistItem::query()->sole()->game_id, 'shop_key' => 'keyshops', 'day' => $yesterday, 'amount' => '5.00', 'currency' => 'EUR']);
    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page->where('dailyDeals', []));
});

it('slevy dne respektují hlídanou platformu', function (): void {
    $yesterday = CarbonImmutable::now((string) config('games.display_timezone'))->subDay()->toDateString();
    DB::table('price_history')->insert(['game_id' => WatchlistItem::query()->sole()->game_id, 'shop_key' => 'keyshops', 'day' => $yesterday, 'amount' => '6.00', 'currency' => 'EUR']);
    WatchlistItem::query()->update(['platform' => 'playstation']);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page->where('dailyDeals', []));
});

it('slevy dne vynechají drobný pokles', function (): void {
    $yesterday = CarbonImmutable::now((string) config('games.display_timezone'))->subDay()->toDateString();
    // Včera 5,45 €, dnes 5,41 € — pokles pod 1 %
    DB::table('price_history')->insert(['game_id' => WatchlistItem::query()->sole()->game_id, 'shop_key' => 'keyshops', 'day' => $yesterday, 'amount' => '5.45', 'currency' => 'EUR']);

    $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page->where('dailyDeals', []));
});
