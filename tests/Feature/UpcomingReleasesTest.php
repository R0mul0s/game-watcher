<?php

/**
 * Nadcházející hry z IGDB — krok cronu, záložka Objevit a sledování (R46).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Enums\MonitoredSource;
use App\Enums\Platform;
use App\Enums\ReleasePrecision;
use App\Models\Game;
use App\Models\SourceStatus;
use App\Models\UpcomingRelease;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** ID her v IGDB ze zachyceného seznamu (tests/Fixtures/igdb/games-upcoming.json). */
const IGDB_GTA_VI = 52189;
const IGDB_FABLE = 92550;
const IGDB_INZOI = 279051;
const IGDB_GOD_OF_WAR_LAUFEY = 404063;
const IGDB_FORZA_HORIZON_6 = 348330;
const IGDB_HELA = 314449;
const IGDB_BLACKFROST = 325596;

const UPCOMING_CRON_URL = '/cron/ceny?token=test-cron-token';

beforeEach(function (): void {
    fakeApis();
    // Seznam IGDB je zachycený 13. 10. 2026
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00', 'UTC'));
});

/**
 * Nadcházející hra podle ID v IGDB.
 */
function upcoming(int $igdbId): UpcomingRelease
{
    return UpcomingRelease::query()->where('igdb_id', $igdbId)->sole();
}

/**
 * Požadavky na IGDB a token Twitche.
 *
 * @return list<Request>
 */
function igdbRequests(string $host): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), $host))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

it('cron stáhne nadcházející hry z IGDB s vydáním po platformách', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk()->assertSeeText('nadcházející hry z IGDB — her: 8');

    expect(UpcomingRelease::query()->count())->toBe(8);

    // Fable: dřívější přístup pro dražší edici (18. 2.) není vydání — vychází 23. 2.
    $fable = upcoming(IGDB_FABLE);
    expect($fable->steam_app_id)->toBe(FABLE_STEAM_ID)
        ->and($fable->releaseOn(Platform::Pc))->toMatchArray(['precision' => ReleasePrecision::Day, 'earlyAccess' => false])
        ->and($fable->pc_release_on?->toDateString())->toBe('2027-02-23')
        ->and($fable->playstation_release_on?->toDateString())->toBe('2027-02-23')
        ->and($fable->coop)->toBeFalse();

    // Forza Horizon 6 na PC vyšla, na PS5 vyjde „2026“
    $forza = upcoming(IGDB_FORZA_HORIZON_6);
    expect($forza->releaseOn(Platform::Pc))->toBeNull()
        ->and($forza->playstation_release_on?->toDateString())->toBe('2026-12-31')
        ->and($forza->playstation_release_precision)->toBe(ReleasePrecision::Year)
        ->and($forza->next_release_on->toDateString())->toBe('2026-12-31');

    // Inzoi je na PC jen v předběžném přístupu (není vydání ani nadcházející), na PS5 v Q1 2027
    $inzoi = upcoming(IGDB_INZOI);
    expect($inzoi->releaseOn(Platform::Pc))->toBeNull()
        ->and($inzoi->playstation_release_precision)->toBe(ReleasePrecision::Quarter)
        ->and($inzoi->playstation_release_on?->toDateString())->toBe('2027-03-31');

    // Start předběžného přístupu je na PC nadcházející vydání se štítkem
    expect(upcoming(IGDB_BLACKFROST)->releaseOn(Platform::Pc))->toMatchArray(['precision' => ReleasePrecision::Year, 'earlyAccess' => true]);

    // Exkluzivita PS5 bez Steamu, co-op podle režimů hry
    expect(upcoming(IGDB_GTA_VI)->steam_app_id)->toBeNull()
        ->and(upcoming(IGDB_GTA_VI)->releaseOn(Platform::Pc))->toBeNull()
        ->and(upcoming(IGDB_HELA)->coop)->toBeTrue()
        ->and(upcoming(IGDB_HELA)->genres)->toContain('Adventure');

    $status = SourceStatus::query()->where('source', MonitoredSource::Igdb->value)->sole();
    expect($status->processed)->toBe(8)->and($status->failures_in_row)->toBe(0);
});

it('posílá dotaz s tokenem aplikace a klíč jen v těle požadavku na token', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();

    [$token] = igdbRequests('id.twitch.tv');
    [$games] = igdbRequests('api.igdb.com');

    expect($token->url())->not->toContain('test-igdb-secret')
        ->and($token['client_secret'])->toBe('test-igdb-secret')
        ->and($games->hasHeader('Authorization', 'Bearer test-token-igdb'))->toBeTrue()
        ->and($games->hasHeader('Client-ID', 'test-igdb-client'))->toBeTrue()
        ->and($games->body())->toContain('release_dates.platform = (6,167)')
        ->and($games->body())->toContain('hypes >= 5');

    // Token drží cache — další běh si ho už nevyžádá
    $this->get(UPCOMING_CRON_URL)->assertOk();
    expect(igdbRequests('id.twitch.tv'))->toHaveCount(1);
});

it('odmítnutý token jednou obnoví a dotaz zopakuje', function (): void {
    fakeApis(['api.igdb.com/v4/games' => Http::sequence()
        ->push(['message' => 'Authorization Failure'], 401)
        ->push(jsonFixture('igdb/games-upcoming'))]);

    $this->get(UPCOMING_CRON_URL)->assertOk()->assertSeeText('nadcházející hry z IGDB — her: 8');

    expect(igdbRequests('id.twitch.tv'))->toHaveCount(2);
});

it('přepíše seznam — hry mimo nový seznam smaže, ITAD ID spárované hry nechá', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    upcoming(IGDB_FABLE)->update(['itad_id' => FABLE_ID, 'title' => 'Starý název']);
    UpcomingRelease::query()->create([
        'igdb_id' => 1,
        'title' => 'Hra, která už vyšla',
        'next_release_on' => '2026-10-01',
    ]);

    $this->get(UPCOMING_CRON_URL)->assertOk();

    expect(UpcomingRelease::query()->where('igdb_id', 1)->exists())->toBeFalse()
        ->and(upcoming(IGDB_FABLE)->itad_id)->toBe(FABLE_ID)
        ->and(upcoming(IGDB_FABLE)->title)->toBe('Fable');
});

it('výpadek IGDB nechá seznam z minula a běh cronu neoznačí jako chybný', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    fakeApis(['api.igdb.com/*' => Http::response(['message' => 'Internal Server Error'], 503)]);

    $this->get(UPCOMING_CRON_URL)->assertOk()->assertSeeText('IGDB neodpovídá');

    expect(UpcomingRelease::query()->count())->toBe(8)
        ->and(SourceStatus::query()->where('source', MonitoredSource::Igdb->value)->sole()->failures_in_row)->toBe(1)
        ->and(SourceStatus::query()->where('source', MonitoredSource::Cron->value)->sole()->failures_in_row)->toBe(0);
});

it('prázdná odpověď IGDB seznam nesmaže', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    fakeApis(['api.igdb.com/v4/games' => Http::response([])]);

    $this->get(UPCOMING_CRON_URL)->assertOk();

    expect(UpcomingRelease::query()->count())->toBe(8)
        ->and(SourceStatus::query()->where('source', MonitoredSource::Igdb->value)->sole()->last_error)->toContain('žádnou nadcházející hru');
});

it('odmítnutý klíč aplikace ukáže stav zdrojů bez tajemství', function (): void {
    fakeApis(['id.twitch.tv/*' => Http::response(['status' => 403, 'message' => 'invalid client secret'], 403)]);

    $this->get(UPCOMING_CRON_URL)->assertOk();

    expect(SourceStatus::query()->where('source', MonitoredSource::Igdb->value)->sole()->last_error)
        ->toContain('HTTP 403')
        ->not->toContain('test-igdb-secret');
});

it('bez klíče aplikace krok přeskočí a IGDB nevolá', function (): void {
    config(['games.sources.igdb.client_id' => '', 'games.sources.igdb.client_secret' => '']);

    $this->get(UPCOMING_CRON_URL)->assertOk()->assertSeeText('IGDB není nastavené');

    expect(igdbRequests('igdb.com'))->toBeEmpty()
        ->and(igdbRequests('twitch.tv'))->toBeEmpty();
});

it('záložka ukáže nadcházející hry od nejbližšího vydání', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();

    $this->actingAs(user())
        ->get('/objevit/nadchazejici')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DiscoverUpcoming')
            ->has('releases', 8)
            ->where('releases.0.title', 'Grand Theft Auto VI')
            ->where('releases.0.releases', [['platforms' => ['playstation'], 'date' => '2026-11-19', 'precision' => 'day', 'earlyAccess' => false]])
            ->where('releases.7.title', 'Inzoi')
            // Stejné datum na obou platformách jako jedna položka
            ->where('releases.6.title', 'Fable')
            ->where('releases.6.releases', [['platforms' => ['pc', 'playstation'], 'date' => '2027-02-23', 'precision' => 'day', 'earlyAccess' => false]])
            ->where('releases.6.imageUrl', fn (string $url): bool => str_starts_with($url, 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/'))
            ->where('releases.6.watchUrl', '/objevit/nadchazejici/'.upcoming(IGDB_FABLE)->id)
            ->where('releases.6.detailUrl', null)
            ->where('filters', ['platform' => null, 'genres' => [], 'coop' => false, 'sort' => 'date'])
            ->where('updatedAt', fn (?string $time): bool => $time !== null)
            ->where('genres.0.name', 'Adventure'));
});

it('filtruje podle platformy, co-op a žánru a řadí podle popularity', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    $titles = fn (string $query): array => array_column(
        $this->actingAs(user())->get('/objevit/nadchazejici'.$query)->viewData('page')['props']['releases'],
        'title',
    );

    // Na PC nevychází GTA VI, God of War, Forza (už vyšla) ani Inzoi (předběžný přístup)
    expect($titles('?platform=pc'))->toBe(['Hela: Of Mice & Magic', 'There Are No Ghosts at the Grand', 'Blackfrost: The Long Dark 2', 'Fable'])
        ->and($titles('?platform=playstation'))->not->toContain('Blackfrost: The Long Dark 2')
        ->and($titles('?coop=1'))->toContain('Hela: Of Mice & Magic')->not->toContain('Fable')
        // Stačí jeden z vybraných žánrů
        ->and($titles('?genres[]=Racing&genres[]=Strategy'))->toBe(['Grand Theft Auto VI', 'Forza Horizon 6', 'Blackfrost: The Long Dark 2'])
        ->and($titles('?sort=popular')[0])->toBe('Grand Theft Auto VI')
        ->and($titles('?sort=popular')[1])->toBe('Fable')
        // Neplatný filtr se jen nepoužije
        ->and($titles('?platform=xbox&sort=nesmysl'))->toHaveCount(8);
});

it('vynechá hry, které uživatel už sleduje', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    $user = watcherOf([FABLE_ID]);

    $this->actingAs($user)
        ->get('/objevit/nadchazejici')
        ->assertInertia(fn (Assert $page) => $page
            ->has('releases', 7)
            ->where('releases', fn ($releases): bool => ! collect($releases)->contains('title', 'Fable')));
});

it('bez staženého seznamu ukáže prázdnou záložku', function (): void {
    $this->actingAs(user())
        ->get('/objevit/nadchazejici')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('releases', 0)->where('updatedAt', null));
});

it('sledování spáruje hru s ITAD podle Steam App ID a převezme ji do katalogu', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    $user = user();
    $fable = upcoming(IGDB_FABLE);

    $this->actingAs($user)
        ->from('/objevit/nadchazejici')
        ->post('/objevit/nadchazejici/'.$fable->id)
        ->assertRedirect('/objevit/nadchazejici');

    $game = Game::query()->where('itad_id', FABLE_ID)->sole();
    expect($fable->fresh()?->itad_id)->toBe(FABLE_ID)
        ->and($user->watchlistItemFor($game)?->platform)->toBeNull()
        ->and(igdbRequests('lookup/v1?appid='.FABLE_STEAM_ID))->toHaveCount(1);

    // Ve hře z katalogu má záložka odkaz na detail — tady už ji ale uživatel sleduje
    $this->actingAs(user())
        ->get('/objevit/nadchazejici')
        ->assertInertia(fn (Assert $page) => $page->where('releases.6.detailUrl', '/hry/'.$game->slug));
});

it('hru jen pro PS5 spáruje podle názvu, hlídá na PlayStationu a pošle připojit PS Store', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    $user = user();

    $response = $this->actingAs($user)->post('/objevit/nadchazejici/'.upcoming(IGDB_GTA_VI)->id);

    $game = Game::query()->where('itad_id', GTA_VI_ID)->sole();
    $response->assertRedirect('/hry/'.$game->slug);
    expect($user->watchlistItemFor($game)?->platform)->toBe(Platform::PlayStation);
});

it('hru, kterou ITAD nezná, nepřidá a řekne proč', function (): void {
    $this->get(UPCOMING_CRON_URL)->assertOk();
    $user = user();

    $this->actingAs($user)
        ->from('/objevit/nadchazejici')
        ->post('/objevit/nadchazejici/'.upcoming(IGDB_GOD_OF_WAR_LAUFEY)->id)
        ->assertRedirect('/objevit/nadchazejici')
        ->assertInertiaFlash('error', '„God of War Laufey“ IsThereAnyDeal zatím nezná — zkus to blíž vydání.');

    expect($user->watchlistItems()->count())->toBe(0);
});
