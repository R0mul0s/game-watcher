<?php

/**
 * Graf vývoje ceny na detailu hry (etapa 7, R28).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Itad\ItadPriceChange;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Domain\Pricing\PriceChart;
use App\Models\Game;
use App\Models\StoreListing;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00', 'Europe/Prague'));
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    $this->user = watcherOf([BIOMUTANT_ID]);
    $this->game = Game::query()->where('itad_id', BIOMUTANT_ID)->with('storeListings')->sole();
});

/**
 * Hodnota řady grafu v daný den.
 *
 * @param  array{days: list<string>, series: list<array{key: string, data: list<float|null>}>}  $chart
 */
function chartValue(array $chart, string $key, string $day): ?float
{
    $series = collect($chart['series'])->firstWhere('key', $key);
    $index = array_search($day, $chart['days'], true);

    return $series === null || $index === false ? null : $series['data'][$index];
}

it('graf se na detailu načte až po zobrazení stránky', function (): void {
    $this->actingAs($this->user)
        ->get('/hry/biomutant')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('chart')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('chart.currency', 'CZK')
                ->where('chart.itadAvailable', true)
                ->where('chart.ownSince', '2026-10-12')
                ->where('chart.days.0', '2025-10-12')
                ->where('chart.days', fn ($days): bool => collect($days)->last() === '2026-10-12')));
});

it('z historie ITAD spočítá nejlevnější obchod a Steam po dnech v měně účtu', function (): void {
    $chart = app(PriceChart::class)->build($this->game, 'CZK', null);

    // 5. 9. 2026: nejlevněji 9,99 € = 243,41 Kč, Steam 39,99 € = 974,36 Kč
    expect(chartValue($chart, PriceChart::SERIES_OFFICIAL, '2026-09-05'))->toBe(243.41)
        ->and(chartValue($chart, PriceChart::SERIES_STEAM, '2026-09-05'))->toBe(974.36)
        // Steam zlevnil 24. 9. večer — den bere nejnižší cenu, která v něm platila
        ->and(chartValue($chart, PriceChart::SERIES_STEAM, '2026-09-24'))->toBe(243.41)
        // 8. 10. večer zdražil — ten den ještě levně, další den draze
        ->and(chartValue($chart, PriceChart::SERIES_STEAM, '2026-10-08'))->toBe(243.41)
        ->and(chartValue($chart, PriceChart::SERIES_STEAM, '2026-10-09'))->toBe(974.36);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/history/v2')
        && $request['id'] === BIOMUTANT_ID
        && $request['country'] === 'CZ'
        && $request->hasHeader('ITAD-API-Key', 'test-itad-key'));
});

it('v eurech graf nepřevádí', function (): void {
    $chart = app(PriceChart::class)->build($this->game, 'EUR', null);

    expect(chartValue($chart, PriceChart::SERIES_STEAM, '2026-09-05'))->toBe(39.99);
});

it('obchody s klíči a PS Store bere z vlastní historie', function (): void {
    fakeApis();
    app(ConnectPlayStationProduct::class)->handle($this->game, 'https://store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME);
    $listing = StoreListing::query()->sole();
    DB::table('price_history')->insert([
        ['game_id' => $this->game->id, 'shop_key' => 'keyshops', 'day' => '2026-10-10', 'amount' => '6.00', 'currency' => 'EUR'],
        ['game_id' => $this->game->id, 'shop_key' => $listing->shopKey(), 'day' => '2026-10-10', 'amount' => '999.00', 'currency' => 'CZK'],
    ]);

    $chart = app(PriceChart::class)->build($this->game->fresh(['storeListings']) ?? $this->game, 'CZK', null);
    $playstation = collect($chart['series'])->firstWhere('key', $listing->shopKey());

    expect(chartValue($chart, PriceChart::SERIES_KEYSHOPS, '2026-10-10'))->toBe(146.19)
        ->and(chartValue($chart, PriceChart::SERIES_KEYSHOPS, '2026-10-09'))->toBeNull()
        ->and(chartValue($chart, $listing->shopKey(), '2026-10-10'))->toBe(999.0)
        ->and($playstation['platform'])->toBe('playstation')
        ->and($playstation['name'])->toContain($listing->name);
});

it('cílovou cenu převede do měny účtu', function (): void {
    WatchlistItem::query()->update(['target_amount' => '10.00', 'target_currency' => 'EUR']);

    $chart = app(PriceChart::class)->build($this->game, 'CZK', WatchlistItem::query()->sole());

    expect($chart['target'])->toBe(243.65);
});

it('historii ITAD drží v cache', function (): void {
    app(PriceChart::class)->build($this->game, 'CZK', null);
    app(PriceChart::class)->build($this->game, 'CZK', null);

    $historyRequests = collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/games/history/v2'));

    expect($historyRequests)->toHaveCount(1);
});

it('výpadek ITAD graf neskryje — jen bez řad z ITAD', function (): void {
    fakeApis(['api.isthereanydeal.com/games/history/v2*' => apiError(503)]);
    DB::table('price_history')->insert(['game_id' => $this->game->id, 'shop_key' => 'keyshops', 'day' => '2026-10-11', 'amount' => '5.50', 'currency' => 'EUR']);

    $chart = app(PriceChart::class)->build($this->game, 'CZK', null);

    expect($chart['itadAvailable'])->toBeFalse()
        ->and(collect($chart['series'])->pluck('key')->all())->toBe([PriceChart::SERIES_KEYSHOPS]);
});

it('hra bez historie má prázdný graf', function (): void {
    DB::table('price_history')->delete();
    fakeApis(['api.isthereanydeal.com/games/history/v2*' => Http::response([])]);

    $chart = app(PriceChart::class)->build($this->game, 'CZK', null);

    expect($chart['days'])->toBe([])->and($chart['series'])->toBe([]);
});

it('záznam historie ITAD přežije cache jako pole', function (): void {
    $change = ItadPriceChange::fromApi(jsonFixture('itad/history-biomutant')[0]);

    expect($change)->not->toBeNull()
        ->and(ItadPriceChange::fromArray($change?->toArray() ?? []))->toEqual($change);
});
