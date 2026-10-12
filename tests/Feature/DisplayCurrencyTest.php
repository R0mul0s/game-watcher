<?php

/**
 * Zobrazení cen v CZK / EUR kurzem ČNB (etapa 3, R14).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Currency\ExchangeRates;
use App\Domain\Pricing\Money;
use App\Models\ExchangeRate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
});

it('převede EUR na CZK a zpět kurzem ČNB', function (): void {
    $rates = new ExchangeRates;

    expect($rates->convert(new Money(2496, 'EUR'), 'CZK')?->cents)->toBe(60815)
        ->and($rates->convert(new Money(60815, 'CZK'), 'EUR')?->cents)->toBe(2496)
        ->and($rates->convert(new Money(100, 'EUR'), 'EUR')?->cents)->toBe(100);
});

it('bez kurzu měny převod nevrátí nic', function (): void {
    expect((new ExchangeRates)->convert(new Money(100, 'XYZ'), 'CZK'))->toBeNull();
});

it('přehled ukáže nejlevnější nabídku a obchody s klíči v korunách', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.current', 'CZK')
            ->where('currency.options', ['CZK', 'EUR'])
            ->where('games.0.prices.best.shop', 'WinGameStore')
            ->where('games.0.prices.best.price', ['amount' => 608.15, 'currency' => 'CZK'])
            ->where('games.0.prices.best.original', ['amount' => 24.96, 'currency' => 'EUR'])
            ->where('games.0.prices.best.cut', 30)
            ->where('games.0.prices.keyshops.price', ['amount' => 131.81, 'currency' => 'CZK']));
});

it('detail seřadí nabídky od nejlevnější a ukáže historická minima', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID]);

    $this->actingAs($user)
        ->get('/hry/control-resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->has('game.prices.offers', 12)
            ->where('game.prices.offers.0.shop', 'AllYouPlay')
            ->where('game.prices.offers.0.price.currency', 'CZK')
            ->where('game.prices.offers.11.cut', 0)
            ->where('game.prices.lows.official.original', ['amount' => 43.19, 'currency' => 'EUR'])
            ->where('game.prices.lows.keyshops.original', ['amount' => 37.07, 'currency' => 'EUR'])
            ->where('game.prices.keyshops.url', 'https://gg.deals/game/control-resonant/')
            ->whereNot('game.prices.fetchedAt', null));
});

it('po přepnutí na EUR ukáže ceny v eurech bez přepočtu', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);

    $this->actingAs($user)
        ->from('/')
        ->put('/nastaveni/mena', ['currency' => 'EUR'])
        ->assertRedirect('/');

    expect($user->refresh()->display_currency)->toBe('EUR');

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('currency.current', 'EUR')
            ->where('games.0.prices.best.price', ['amount' => 24.96, 'currency' => 'EUR'])
            ->where('games.0.prices.best.original', null));
});

it('odmítne měnu, kterou aplikace nenabízí', function (): void {
    $user = user();

    $this->actingAs($user)
        ->put('/nastaveni/mena', ['currency' => 'USD'])
        ->assertSessionHasErrors('currency');

    expect($user->refresh()->display_currency)->toBe('CZK');
});

it('bez uloženého kurzu ukáže ceny v měně obchodu', function (): void {
    ExchangeRate::query()->delete();
    $user = watcherOf([BIOMUTANT_ID]);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('games.0.prices.best.price', ['amount' => 24.96, 'currency' => 'EUR'])
            ->where('games.0.prices.best.original', null));
});
