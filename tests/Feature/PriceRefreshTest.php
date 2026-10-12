<?php

/**
 * Stažení a uložení cen her (etapa 3, R9).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Pricing\Actions\RefreshPrices;
use App\Enums\PriceScope;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    fakeApis();
});

it('uloží nabídky oficiálních obchodů, obchody s klíči a historická minima', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();

    $winGameStore = $game->currentPrices()->where('shop_key', 'itad-64')->sole();
    $keyshops = $game->currentPrices()->where('shop_key', CurrentPrice::KEYSHOPS_KEY)->sole();

    expect($game->currentPrices()->where('source', PriceSource::Itad->value)->count())->toBe(15)
        ->and($winGameStore->shop_name)->toBe('WinGameStore')
        ->and($winGameStore->amount)->toBe('24.96')
        ->and($winGameStore->regular_amount)->toBe('35.66')
        ->and($winGameStore->currency)->toBe('EUR')
        ->and($winGameStore->cut)->toBe(30)
        ->and($winGameStore->drm)->toBe('Steam')
        ->and($keyshops->source)->toBe(PriceSource::GgDeals)
        ->and($keyshops->amount)->toBe('5.41')
        ->and($keyshops->url)->toBe('https://gg.deals/game/biomutant/')
        ->and(PriceLow::query()->where('scope', PriceScope::Official->value)->sole()->amount)->toBe('6.72')
        ->and(PriceLow::query()->where('scope', PriceScope::Keyshops->value)->sole()->amount)->toBe('5.24');
});

it('ITAD ptá jednou dávkou a ceny chce pro Českou republiku', function (): void {
    $games = collect([BIOMUTANT_ID, CONTROL_RESONANT_ID, ALAN_WAKE_2_ID])
        ->map(fn (string $id): Game => Game::factory()->create(['itad_id' => $id, 'steam_app_id' => null]));
    fakeApis();

    $result = app(RefreshPrices::class)->handle($games);

    expect($result->updatedBy(PriceSource::Itad))->toBe(3)
        ->and($result->hasFailures())->toBeFalse();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/games/prices/v3?country=CZ')
        && count($request->data()) === 3);
});

it('hra mimo Steam nemá cenu obchodů s klíči — gg.deals hledá jen podle Steam App ID', function (): void {
    watcherOf([ALAN_WAKE_2_ID]);

    expect(CurrentPrice::query()->where('shop_key', CurrentPrice::KEYSHOPS_KEY)->exists())->toBeFalse()
        ->and(CurrentPrice::query()->count())->toBe(4);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'gg.deals'));
});

it('obchod, který hru přestal nabízet, z cen zmizí', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();
    $prices = jsonFixture('itad/prices');
    $biomutant = collect($prices)->firstWhere('id', BIOMUTANT_ID);
    $biomutant['deals'] = array_slice($biomutant['deals'], 0, 2);
    fakeApis(['api.isthereanydeal.com/games/prices/v3*' => Http::response([$biomutant])]);

    app(RefreshPrices::class)->handle(collect([$game]));

    expect($game->currentPrices()->where('source', PriceSource::Itad->value)->count())->toBe(2)
        ->and($game->currentPrices()->where('shop_key', CurrentPrice::KEYSHOPS_KEY)->exists())->toBeTrue();
});

it('denní historie drží nejnižší cenu dne', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();
    $biomutant = collect(jsonFixture('itad/prices'))->firstWhere('id', BIOMUTANT_ID);
    $biomutant['deals'][0]['price']['amountInt'] = 3000;
    fakeApis(['api.isthereanydeal.com/games/prices/v3*' => Http::response([$biomutant])]);

    app(RefreshPrices::class)->handle(collect([$game]));

    $history = DB::table('price_history')->where('game_id', $game->id)->where('shop_key', 'itad-64')->get();
    expect($history)->toHaveCount(1)
        ->and($history->first()->amount)->toBe('24.96')
        ->and($game->currentPrices()->where('shop_key', 'itad-64')->sole()->amount)->toBe('30.00');
});

it('výpadek gg.deals nezastaví ceny z ITAD a naopak', function (): void {
    $game = Game::factory()->create(['itad_id' => BIOMUTANT_ID, 'steam_app_id' => 597820]);

    fakeApis(['api.gg.deals/*' => Http::response(['success' => false, 'data' => ['message' => 'Too Many Requests']], 429)]);
    $result = app(RefreshPrices::class)->handle(collect([$game]));
    expect($result->failed)->toBe([PriceSource::GgDeals])
        ->and($result->updatedBy(PriceSource::Itad))->toBe(1);

    fakeApis(['api.isthereanydeal.com/*' => apiError(503)]);
    $result = app(RefreshPrices::class)->handle(collect([$game]));
    expect($result->failed)->toBe([PriceSource::Itad])
        ->and($result->updatedBy(PriceSource::GgDeals))->toBe(1);
});

it('klíč gg.deals se do logu nedostane — API ho bere jen v URL', function (): void {
    Log::spy();
    $game = Game::factory()->create(['itad_id' => BIOMUTANT_ID, 'steam_app_id' => 597820]);
    fakeApis(['api.gg.deals/*' => fn (Request $request) => throw new ConnectionException('cURL error 28: timeout for '.$request->url())]);

    app(RefreshPrices::class)->handle(collect([$game]));

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'key=***')
        && ! str_contains($message, 'test-ggdeals-key'));
});
