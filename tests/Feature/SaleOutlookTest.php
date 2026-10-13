<?php

/**
 * „Kup, nebo počkej?“ z historie cen ITAD a kalendáře výprodejů (R42).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Itad\ItadPriceChange;
use App\Domain\Pricing\Money;
use App\Domain\Pricing\SaleOutlook;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** ID obchodu Steam v ITAD (games.chart.steam_shop_id). */
const OUTLOOK_STEAM_ID = 61;

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00', 'Europe/Prague'));
    app(ImportExchangeRates::class)->handle();
    $this->user = watcherOf([BIOMUTANT_ID]);
    $this->game = Game::query()->sole();
});

/**
 * Doporučení pro BIOMUTANT v CZK.
 *
 * @return array<string, mixed>|null
 */
function outlook(?WatchlistItem $item = null): ?array
{
    return app(SaleOutlook::class)->build(test()->game, $item, 'CZK');
}

/**
 * Podstrčí do cache vymyšlenou historii Steamu — změny [den, cena v €, sleva].
 *
 * @param  list<array{0: string, 1: string, 2: int}>  $changes
 */
function steamHistory(array $changes): void
{
    Cache::put('itad:history:v2:'.BIOMUTANT_ID, array_map(fn (array $change): array => (new ItadPriceChange(
        CarbonImmutable::parse($change[0].' 09:00', 'Europe/Prague')->utc(),
        OUTLOOK_STEAM_ID,
        'Steam',
        Money::fromDecimal($change[1], 'EUR'),
        $change[2],
    ))->toArray(), $changes), 3600);
}

it('ze zachycené historie: levněji skoro pořád, počkej', function (): void {
    // BIOMUTANT stojí dnes nejlevněji 24,96 € (−30 %), v menších obchodech
    // ale býval kolem 7 € většinu roku
    expect(outlook())
        ->verdict->toBe('wait')
        ->reason->toBe('pattern')
        ->currentCut->toBe(30)
        ->cheaperShare->toBe(96)
        ->yearLow->toBe(['price' => ['amount' => 170.31, 'currency' => 'CZK'], 'cut' => 80, 'date' => '2025-10-13'])
        ->nearLow->toBe(['now' => false, 'daysAgo' => 64, 'intervalDays' => 44])
        // Zimní výprodej Steamu začne za 65 dní — ještě mimo upcoming_days
        ->upcoming->toBeNull()
        ->since->toBe('2025-10-13');
});

it('před výprodejem z kalendáře ukáže, za kolik byla hra loni', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'Europe/Prague'));

    // Loni ve stejném období (17. 12. – 4. 1.) Steam −75 % za 9,99 € = 243 Kč
    expect(outlook())
        ->verdict->toBe('wait')
        ->reason->toBe('event')
        ->upcoming->toBe([
            'key' => 'steam_winter',
            'start' => '2026-12-17',
            'end' => '2027-01-04',
            'daysUntil' => 46,
            'ongoing' => false,
            'lastYear' => ['price' => ['amount' => 243.41, 'currency' => 'CZK'], 'cut' => 75],
        ]);
});

it('na ročním minimu doporučí koupit', function (): void {
    steamHistory([['2025-10-01', '39.99', 0], ['2026-03-01', '19.99', 50], ['2026-03-10', '39.99', 0], ['2026-10-10', '15.99', 60]]);

    expect(outlook())->verdict->toBe('buy')->reason->toBe('low')->currentCut->toBe(60)
        ->and(outlook()['nearLow']['now'])->toBeTrue();
});

it('když byla levněji jen málokdy, je to dobrá cena', function (): void {
    // Levněji (14,99 €) jen 9 dní v březnu
    steamHistory([['2025-10-01', '39.99', 0], ['2026-03-01', '14.99', 63], ['2026-03-10', '39.99', 0], ['2026-10-10', '19.99', 50]]);

    expect(outlook())->verdict->toBe('good')->reason->toBe('cheap')->cheaperShare->toBe(2);
});

it('běžná cena bez výprodeje na obzoru je bez doporučení', function (): void {
    // Levněji 1. 3. – 29. 6. (den změny počítá nejnižší cenu dne) = 121 dní z roku = 33 %
    steamHistory([['2025-10-01', '39.99', 0], ['2026-03-01', '14.99', 63], ['2026-06-29', '39.99', 0]]);

    expect(outlook())->verdict->toBe('neutral')->reason->toBe('typical')->cheaperShare->toBe(33);
});

it('krátká historie, výpadek ITAD a hra jen na PlayStation doporučení nemají', function (): void {
    steamHistory([['2026-10-01', '39.99', 0]]);
    expect(outlook())->toBeNull();

    Cache::flush();
    fakeApis(['api.isthereanydeal.com/games/history/v2*' => apiError(503)]);
    expect(outlook())->toBeNull();

    $item = WatchlistItem::query()->sole();
    $item->update(['platform' => Platform::PlayStation]);
    expect(outlook($item))->toBeNull();
});

it('detail ho načte odloženě spolu s grafem z jedné historie ITAD', function (): void {
    $this->actingAs($this->user)
        ->get('/hry/biomutant')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('outlook')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('outlook.verdict', 'wait')
                ->where('chart.itadAvailable', true)));

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'games/history/v2')))->toHaveCount(1);
});
