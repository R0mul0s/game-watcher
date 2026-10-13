<?php

/**
 * Stav zdrojů — zápis z cronu, stránka, upozornění na přehledu a monitoring (R29).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Monitoring\SourceMonitor;
use App\Enums\MonitoredSource;
use App\Models\Game;
use App\Models\SourceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const STATUS_CRON_URL = '/cron/ceny?token=test-cron-token';
const HEALTH_URL = '/health/zdroje';

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-11 18:00', 'Europe/Prague'));
});

/**
 * Uložený stav zdroje.
 */
function sourceStatus(MonitoredSource $source): SourceStatus
{
    return SourceStatus::query()->where('source', $source->value)->sole();
}

it('cron zapíše stav každého zdroje i celého běhu', function (): void {
    watcherOf([BIOMUTANT_ID, ALAN_WAKE_2_ID]);
    fakeApis();

    $this->get(STATUS_CRON_URL)->assertOk();

    expect(SourceStatus::query()->count())->toBe(count(MonitoredSource::cases()))
        ->and(sourceStatus(MonitoredSource::Itad))
        ->processed->toBe(2)
        ->expected->toBe(2)
        ->failures_in_row->toBe(0)
        ->last_success_at->not->toBeNull()
        // gg.deals jen hry se Steam App ID — Alan Wake 2 je jen na Epicu
        ->and(sourceStatus(MonitoredSource::GgDeals))
        ->processed->toBe(1)
        ->expected->toBe(1)
        ->and(sourceStatus(MonitoredSource::Cron)->last_error)->toBeNull();
});

it('výpadek zdroje zapíše se zprávou, po zotavení chyby vynuluje a zprávu nechá', function (): void {
    watcherOf([BIOMUTANT_ID]);
    fakeApis(['api.isthereanydeal.com/*' => apiError(503)]);

    $this->get(STATUS_CRON_URL)->assertInternalServerError();

    expect(sourceStatus(MonitoredSource::Itad))
        ->failures_in_row->toBe(1)
        ->last_error->toBe('HTTP 503')
        ->last_success_at->toBeNull()
        ->and(sourceStatus(MonitoredSource::GgDeals)->failures_in_row)->toBe(0)
        ->and(sourceStatus(MonitoredSource::Cron)->last_error)->toBe('Selhalo: ceny');

    fakeApis();
    $this->travel(4)->hours();
    $this->get(STATUS_CRON_URL)->assertOk();

    expect(sourceStatus(MonitoredSource::Itad))
        ->failures_in_row->toBe(0)
        ->last_success_at->not->toBeNull()
        ->last_error->toBe('HTTP 503');
});

it('zprávu jiné než zdrojové výjimky neukládá — mohla by obsahovat tajemství', function (): void {
    $monitor = app(SourceMonitor::class);

    expect($monitor->describe(new ItadUnavailable('HTTP 503')))->toBe('HTTP 503')
        ->and($monitor->describe(new RuntimeException('https://api.gg.deals/?key=tajne')))->toBe(__('app.monitoring.unexpected_error'));
});

it('monitoring hlásí výpadek, dokud cron neproběhl', function (): void {
    $this->get(HEALTH_URL)
        ->assertServiceUnavailable()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSeeText('Běh cronu — VÝPADEK: zatím neproběhlo');
});

it('po úspěšném běhu monitoring hlásí OK se stářím', function (): void {
    $this->get(STATUS_CRON_URL)->assertOk();
    $this->travel(30)->minutes();

    $this->get(HEALTH_URL)
        ->assertOk()
        ->assertSeeText('Běh cronu — OK: poslední běh před 30 min')
        ->assertSeeText('IsThereAnyDeal — oficiální obchody — OK: poslední úspěch před 30 min');
});

it('jeden neúspěšný běh je jen chyba, výpadek je až zestárlý úspěch', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $this->get(STATUS_CRON_URL)->assertOk();

    fakeApis(['api.isthereanydeal.com/*' => apiError(503)]);
    foreach ([4, 4] as $hours) {
        $this->travel($hours)->hours();
        $this->get(STATUS_CRON_URL)->assertInternalServerError();
    }

    // Úspěch před 8 h — ještě v mezi 9 h
    $this->get(HEALTH_URL)
        ->assertOk()
        ->assertSeeText('IsThereAnyDeal — oficiální obchody — CHYBA: poslední pokus selhal, úspěch před 480 min')
        ->assertSeeText('Běh cronu — CHYBA');

    $this->travel(61)->minutes();

    $this->get(HEALTH_URL)
        ->assertServiceUnavailable()
        ->assertSeeText('IsThereAnyDeal — oficiální obchody — VÝPADEK: poslední úspěch před 541 min');
});

it('cron, který přestal běžet, je výpadek', function (): void {
    $this->get(STATUS_CRON_URL)->assertOk();
    $this->travel(301)->minutes();

    $this->get(HEALTH_URL)
        ->assertServiceUnavailable()
        ->assertSeeText('Běh cronu — VÝPADEK: poslední běh před 301 min — cron neběží');
});

it('výpadek Steamu monitoring nehlásí — údaje jsou doplňkové (R25)', function (): void {
    $this->get(STATUS_CRON_URL)->assertOk();
    sourceStatus(MonitoredSource::Steam)->update(['last_success_at' => CarbonImmutable::now()->subDays(2)]);

    $this->get(HEALTH_URL)
        ->assertOk()
        ->assertSeeText('Steam — žánry a režimy — (nehlídá se) VÝPADEK');
});

it('stránka stavu ukáže zdroje v pořadí kroků cronu', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);
    // Hra bez žánrů — cron se na ně zeptá Steamu
    $user->watchlistItems()->create(['game_id' => Game::factory()->create(['steam_app_id' => IT_TAKES_TWO_STEAM_ID])->id]);
    fakeApis(['store.steampowered.com/*' => Http::response('', 429)]);
    $this->get(STATUS_CRON_URL);

    $this->actingAs(user())
        ->get('/stav-zdroju')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sources')
            ->has('sources', count(MonitoredSource::cases()))
            ->where('sources.0.source', 'cron')
            ->where('sources.0.state', 'ok')
            ->where('sources.2.source', 'itad')
            ->where('sources.2.processed', 1)
            ->where('sources.8.source', 'steam')
            // Selhal hned napoprvé — bez úspěchu je to výpadek
            ->where('sources.8.state', 'stale')
            ->where('sources.8.critical', false)
            ->where('sources.8.lastError', __('app.monitoring.steam_failed')));
});

it('stránka stavu je jen pro přihlášené', function (): void {
    $this->get('/stav-zdroju')->assertRedirect('/prihlaseni');
});

it('přehled upozorní na zdroje ve výpadku', function (): void {
    $user = user();

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('sourcesDown', ['cron', 'exchange_rates', 'itad', 'ggdeals', 'playstation', 'alerts', 'weekly_digest', 'canary']));

    $this->get(STATUS_CRON_URL)->assertOk();

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page->where('sourcesDown', []));
});
