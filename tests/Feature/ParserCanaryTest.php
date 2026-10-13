<?php

/**
 * Denní kontrola parserů na referenčních hrách (R48).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Monitoring\SourceMonitor;
use App\Enums\MonitoredSource;
use App\Enums\SourceState;
use App\Models\SourceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

const CANARY_CRON_URL = '/cron/ceny?token=test-cron-token';

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-11 18:00', 'Europe/Prague'));
});

/**
 * Uložený stav kontroly parserů.
 */
function canaryStatus(): SourceStatus
{
    return SourceStatus::query()->where('source', MonitoredSource::Canary->value)->sole();
}

it('zkontroluje parsery jednou denně a nalezené údaje zapíše jako úspěch', function (): void {
    $this->get(CANARY_CRON_URL)->assertOk()->assertSeeText('kontrola parserů — v pořádku zdrojů: 6');

    expect(canaryStatus())
        ->processed->toBe(6)
        ->failures_in_row->toBe(0);

    // Další běh cronu za 4 hodiny kontrolu přeskočí
    $this->travel(4)->hours();
    $this->get(CANARY_CRON_URL)->assertSeeText('kontrola parserů — dnes už proběhla');
    // Metacritic volá jen kontrola (žádná hra ho nepotřebuje) — za dva běhy jednou
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'metacritic.com'))->count())->toBe(1);

    // Za den znovu
    $this->travel(20)->hours();
    $this->get(CANARY_CRON_URL)->assertSeeText('kontrola parserů — v pořádku zdrojů: 6');
});

it('tiše chybějící údaj ohlásí jako výpadek, běh cronu ale neoznačí (R48)', function (): void {
    // Steam přestal posílat seznam jazyků — parser by vrátil neznámou češtinu bez chyby
    $withoutLanguages = jsonFixture('steam/appdetails-it-takes-two');
    unset($withoutLanguages[(string) IT_TAKES_TWO_STEAM_ID]['data']['supported_languages']);
    fakeApis(['store.steampowered.com/api/appdetails*' => Http::response($withoutLanguages)]);

    $this->get(CANARY_CRON_URL)
        ->assertOk()
        ->assertSeeText('kontrola parserů — CHYBA: Steam: chybí seznam jazyků');

    expect(canaryStatus())
        ->failures_in_row->toBe(1)
        ->last_error->toBe('Steam: chybí seznam jazyků');

    $this->get('/health/zdroje')
        ->assertStatus(503)
        ->assertSeeText('Kontrola parserů');
});

it('změněný formát stránky PS Store ohlásí zprávou zdroje', function (): void {
    fakeApis(['store.playstation.com/cs-cz/product/'.PSN_UNTIL_DAWN => Http::response('<html><body>Nový web</body></html>')]);

    $this->get(CANARY_CRON_URL)->assertOk();

    expect(canaryStatus()->last_error)->toStartWith('PS Store: Stránka')
        ->toContain('PS Store nejspíš změnil web');
});

it('kontrola starší než den a kus je výpadek, čerstvá ne', function (): void {
    $this->get(CANARY_CRON_URL)->assertOk();
    $this->get('/health/zdroje')->assertSeeText('Kontrola parserů — PS Store, Steam, HowLongToBeat, Metacritic, Xzone, Heureka — OK');

    // Kontrola se nepovede vynechat déle než 30 h — cron mezitím neběžel
    $this->travel(31)->hours();
    $state = collect(app(SourceMonitor::class)->report())
        ->firstWhere('source', MonitoredSource::Canary)?->state;

    expect($state)->toBe(SourceState::Stale);
});
