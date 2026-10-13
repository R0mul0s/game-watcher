<?php

/**
 * Délka hraní z HowLongToBeat a cena za hodinu (R44).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Hltb\Actions\RefreshPlaytimes;
use App\Domain\Hltb\HltbGame;
use App\Domain\Hltb\HltbUrl;
use App\Enums\MonitoredSource;
use App\Models\Game;
use App\Models\SourceStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
});

/**
 * Požadavky na HowLongToBeat s danou cestou.
 *
 * @return list<Request>
 */
function hltbRequests(string $path): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'howlongtobeat.com'.$path))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

it('při přidání hry najde délku hraní podle názvu', function (): void {
    watcherOf([BIOMUTANT_ID]);

    // 46 692 / 88 136 / 152 482 s ze zachyceného hledání
    expect(Game::query()->sole())
        ->hltb_id->toBe(48568)
        ->hltb_main_minutes->toBe(778)
        ->hltb_extra_minutes->toBe(1469)
        ->hltb_complete_minutes->toBe(2541)
        ->hltb_checked_at->not->toBeNull();

    $search = array_values(array_filter(hltbRequests('/api/search/site'), fn (Request $request): bool => $request->method() === 'POST'));
    expect($search)->toHaveCount(1)
        ->and(hltbRequests('/api/search/site/init'))->toHaveCount(1)
        ->and($search[0]->header('x-auth-token'))->toBe(['dGVzdC10b2tlbg'])
        ->and($search[0]['searchTerms'])->toBe(['BIOMUTANT']);
});

it('název porovná jen podle písmen a číslic', function (string $itadId, int $hltbId): void {
    watcherOf([$itadId]);

    expect(Game::query()->sole()->hltb_id)->toBe($hltbId);
})->with([
    'CONTROL Resonant = Control: Resonant' => [CONTROL_RESONANT_ID, 116190],
    'Alan Wake 2' => [ALAN_WAKE_2_ID, 101237],
]);

it('víc stejných názvů rozliší rokem vydání, přípona edice nevadí', function (): void {
    $doom = fn (int $id, int $year): HltbGame => new HltbGame($id, 'DOOM', 600, null, null, $year);
    $game = Game::factory()->make(['title' => 'DOOM', 'release_date' => '2016-05-13']);

    expect(RefreshPlaytimes::match($game, [$doom(1, 1993), $doom(2, 2016)])?->id)->toBe(2)
        ->and(RefreshPlaytimes::match(Game::factory()->make(['title' => 'DOOM', 'release_date' => null]), [$doom(1, 1993), $doom(2, 2016)]))->toBeNull()
        ->and(RefreshPlaytimes::match(
            Game::factory()->make(['title' => 'Sekiro™: Shadows Die Twice - GOTY Edition']),
            [new HltbGame(3, 'Sekiro: Shadows Die Twice', 1800, null, null, 2019)],
        )?->id)->toBe(3);
});

it('nenalezenou hru označí a nabídne odkaz', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);
    $game = Game::factory()->create(['title' => 'Hra, kterou HLTB nezná', 'slug' => 'neznama']);
    $user->watchlistItems()->create(['game_id' => $game->id]);

    app(RefreshPlaytimes::class)->handle(collect([$game]));

    expect($game->fresh())->hltb_id->toBeNull()->hltb_checked_at->not->toBeNull();
    $this->actingAs($user)->get('/hry/neznama')->assertInertia(fn (Assert $page) => $page
        ->where('game.playtime.main', null)
        ->where('game.playtime.checked', true)
        ->where('game.playtime.linkUrl', '/hry/neznama/delka-hrani'));
});

it('odmítnutý token jednou obnoví', function (): void {
    fakeApis(['howlongtobeat.com/api/search/site' => Http::sequence()
        ->push(['error' => 'Session expired or invalid fingerprint'], 403)
        ->push(jsonFixture('hltb/search-biomutant'))]);

    watcherOf([BIOMUTANT_ID]);

    expect(Game::query()->sole()->hltb_id)->toBe(48568)
        ->and(hltbRequests('/api/search/site/init'))->toHaveCount(2);
});

it('odkaz od uživatele připojí hru, jiný hostitel ne', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);

    $this->actingAs($user)
        ->put('/hry/biomutant/delka-hrani', ['url' => 'https://evil.example/game/101237'])
        ->assertSessionHasErrors(['url' => __('app.hltb.not_found')]);

    $this->actingAs($user)
        ->from('/hry/biomutant')
        ->put('/hry/biomutant/delka-hrani', ['url' => 'howlongtobeat.com/game/101237'])
        ->assertRedirect('/hry/biomutant');

    expect(Game::query()->sole())->hltb_id->toBe(101237)->hltb_main_minutes->toBe(1143);
    expect(HltbUrl::parse('https://www.howlongtobeat.com/game/48568/reviews'))->toBe(48568)
        ->and(HltbUrl::parse('https://howlongtobeat.com/user/48568'))->toBeNull();
});

it('cron obnoví známou hru ze stránky, změnu formátu ohlásí a údaje nesmaže', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $this->travel((int) config('games.sources.hltb.refresh_days') + 1)->days();
    fakeApis(['howlongtobeat.com/game/48568' => Http::response('<html><body>Nový web</body></html>')]);

    $this->get('/cron/ceny?token='.config('games.cron.token'));

    expect(SourceStatus::query()->where('source', MonitoredSource::Hltb->value)->sole()->failures_in_row)->toBe(1)
        ->and(Game::query()->sole()->hltb_main_minutes)->toBe(778);

    fakeApis();
    $this->get('/cron/ceny?token='.config('games.cron.token'))->assertSee('Délka hraní z HowLongToBeat — her: 1');
    expect(hltbRequests('/api/search/site'))->toBeEmpty()
        ->and(SourceStatus::query()->where('source', MonitoredSource::Hltb->value)->sole()->failures_in_row)->toBe(0);
});

it('karta ukáže délku a cenu za hodinu, přehled jde řadit podle ní', function (): void {
    $user = watcherOf([BIOMUTANT_ID, ALAN_WAKE_2_ID]);

    $response = $this->actingAs($user)->get('/?sort=per_hour')->assertOk();
    $games = $response->viewData('page')['props']['games'];

    expect(array_column($games, 'title'))->toBe(['BIOMUTANT', 'Alan Wake 2'])
        ->and($games[0]['playtime'])->toBe(778)
        ->and($games[0]['pricePerHour']['cents'])->toBe((int) round($games[0]['ranked']['effectiveCents'] * 60 / 778))
        ->and($games[0]['pricePerHour']['price']['currency'])->toBe('CZK');
});
