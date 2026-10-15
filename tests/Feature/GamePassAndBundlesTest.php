<?php

/**
 * Game Pass (R50) a bundly (R51) z IsThereAnyDeal — cron, upozornění a zobrazení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendPriceAlerts;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Pricing\Actions\RefreshBundles;
use App\Domain\Pricing\Actions\RefreshSubscriptions;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\GameBundle;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/** Darksiders III — v zachyceném bundlu GreenManGaming za 9,99 € (jediná úroveň). */
const DARKSIDERS_3_ID = '018d937e-f821-70ba-8ba1-0070aef4d688';

/** Heavy Rain — v bundlu „Build your own“ od Fanatical (úroveň bez ceny). */
const HEAVY_RAIN_ID = '018d937e-f99a-7194-a303-07dd8f22d0b4';

const OFFERS_CRON_URL = '/cron/ceny?token=test-cron-token';

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    Mail::fake();
});

/**
 * Účet sledující hry katalogu, které ITAD nemá (vytvořené přímo s ITAD ID).
 *
 * @param  list<string>  $itadIds
 * @param  array<string, mixed>  $attributes
 */
function bundleWatcher(array $itadIds, array $attributes = []): User
{
    $user = user($attributes);
    foreach ($itadIds as $itadId) {
        $user->watchlistItems()->create(['game_id' => Game::factory()->create(['itad_id' => $itadId])->id]);
    }

    return $user;
}

it('cron zjistí Game Pass sledovaných her jedním dávkovým dotazem (R50)', function (): void {
    watcherOf([FABLE_ID, BIOMUTANT_ID]);

    $this->get(OFFERS_CRON_URL)->assertSeeText('Game Pass z ITAD — her v Game Passu: 1');

    expect(Game::query()->where('itad_id', FABLE_ID)->sole()->in_game_pass)->toBeTrue()
        ->and(Game::query()->where('itad_id', BIOMUTANT_ID)->sole()->in_game_pass)->toBeFalse();
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'games/subs/v1'))->count())->toBe(1);
});

it('cron uloží bundly sledovaných her s cenou nejlevnější úrovně (R51)', function (): void {
    bundleWatcher([DARKSIDERS_3_ID, HEAVY_RAIN_ID]);
    // Nesledovaná hra v bundlu se neukládá
    Game::factory()->create(['itad_id' => '018d937f-062b-73cb-8b77-d067302bc69e']);

    $this->get(OFFERS_CRON_URL)->assertSeeText('bundly z ITAD — her v bundlu: 2');

    $darksiders = GameBundle::query()->whereHas('game', fn ($query) => $query->where('itad_id', DARKSIDERS_3_ID))->sole();
    expect(GameBundle::query()->count())->toBe(2)
        ->and($darksiders)
        ->title->toBe('The Darksiders Collection Bundle')
        ->shop->toBe('GreenManGaming')
        ->amount->toBe('9.99')
        ->currency->toBe('EUR')
        ->and($darksiders->expires_at?->toIso8601String())->toBe('2026-10-27T18:00:00+00:00')
        ->and(GameBundle::query()->whereHas('game', fn ($query) => $query->where('itad_id', HEAVY_RAIN_ID))->sole()->amount)->toBeNull();
});

it('výpadek ITAD nechá bundly z minula a běh cronu neoznačí', function (): void {
    bundleWatcher([DARKSIDERS_3_ID]);
    app(RefreshBundles::class)->handle();
    fakeApis(['api.isthereanydeal.com/bundles/v1*' => apiError(503)]);

    $this->get(OFFERS_CRON_URL)
        ->assertOk()
        ->assertSeeText('bundly z ITAD — ITAD neodpovídá (zůstávají z minula)');

    expect(GameBundle::query()->count())->toBe(1);
});

it('účtu s Game Passem upozorní jednou, bez Game Passu ne (R50)', function (bool $hasGamePass, int $alerts): void {
    $user = watcherOf([FABLE_ID], ['has_game_pass' => $hasGamePass]);
    app(RefreshSubscriptions::class)->handle();

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe($alerts)
        ->and(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);

    if ($alerts > 0) {
        expect(lastAlert($user)->headline)->toBe('Fable — je v Game Passu, který máš')
            ->and(lastAlert($user)->price)->toBe('');
    }
})->with([
    'má Game Pass' => [true, 1],
    'nemá Game Pass' => [false, 0],
]);

it('po odchodu hry z Game Passu stav smaže a návrat upozorní znovu (R50)', function (): void {
    watcherOf([FABLE_ID], ['has_game_pass' => true]);
    app(RefreshSubscriptions::class)->handle();
    app(SendPriceAlerts::class)->handle();

    Game::query()->update(['in_game_pass' => false]);
    app(SendPriceAlerts::class)->handle();
    expect(WatchlistItem::query()->sole()->game_pass_notified_at)->toBeNull();

    Game::query()->update(['in_game_pass' => true]);
    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
});

it('upozorní na každý bundle jednou, s cenou v měně účtu (R51)', function (): void {
    $user = bundleWatcher([DARKSIDERS_3_ID]);
    app(RefreshBundles::class)->handle();

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1)
        ->and(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0)
        ->and(WatchlistItem::query()->sole()->notified_bundles)->toBe([16655]);

    $record = lastAlert($user);
    expect(plainSpaces($record->headline))->toContain(' v bundlu za ')
        ->and($record->shop)->toBe('na GreenManGaming')
        ->and(plainSpaces($record->reason))->toBe('v bundlu „The Darksiders Collection Bundle“ do 27. 10.')
        ->and(plainSpaces($record->price))->toEndWith('Kč');
});

it('hra hlídaná jen na PlayStationu na bundle neupozorní (R51)', function (): void {
    bundleWatcher([DARKSIDERS_3_ID]);
    WatchlistItem::query()->update(['platform' => Platform::PlayStation->value]);
    app(RefreshBundles::class)->handle();

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
});

it('karta ukáže Game Pass a nejlevnější bundle, detail všechny bundly', function (): void {
    $user = bundleWatcher([DARKSIDERS_3_ID]);
    Game::query()->update(['in_game_pass' => true]);
    app(RefreshBundles::class)->handle();

    $card = $this->actingAs($user)->get('/')->viewData('page')['props']['games'][0];
    expect($card['gamePass'])->toBeTrue()
        ->and($card['bundle']['shop'])->toBe('GreenManGaming')
        ->and($card['bundle']['price']['currency'])->toBe('CZK');

    $game = Game::query()->sole();
    $detail = $this->actingAs($user)->get('/hry/'.$game->slug)->viewData('page')['props']['game'];
    expect($detail['gamePass'])->toBe(['leavesAt' => null])
        ->and($detail['bundles'])->toHaveCount(1)
        ->and($detail['bundles'][0]['title'])->toBe('The Darksiders Collection Bundle');
});
