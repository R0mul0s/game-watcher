<?php

/**
 * Cron URL — kurzy ČNB, ceny a úklid (etapa 3, R2).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Models\CurrentPrice;
use App\Models\ExchangeRate;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const CRON_URL = '/cron/ceny?token=test-cron-token';

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-11 18:00', 'Europe/Prague'));
});

it('bez správného tokenu se tváří, že neexistuje', function (string $url): void {
    $this->get($url)->assertNotFound();
})->with(['/cron/ceny', '/cron/ceny?token=spatne']);

it('s prázdným tokenem v konfiguraci je vypnutá', function (): void {
    config(['games.cron.token' => '']);

    $this->get('/cron/ceny?token=')->assertNotFound();
});

it('stáhne kurz ČNB a ceny sledovaných her a vypíše souhrn', function (): void {
    watcherOf([BIOMUTANT_ID, ALAN_WAKE_2_ID]);
    fakeApis();

    $this->get(CRON_URL)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSeeText('kurzy ČNB — uložen lístek z 9. 10. 2026')
        ->assertSeeText('ceny — ITAD her: 2, gg.deals her: 1');

    expect(ExchangeRate::query()->where('currency', 'EUR')->sole()->rate)->toBe('24.365000');
});

it('kurz, který už je uložený, nestahuje znovu', function (): void {
    $this->get(CRON_URL)->assertOk();
    // Lístek z pátku je o víkendu poslední — další pokus až za hodinu
    $this->get(CRON_URL)->assertSeeText('kurzy ČNB — aktuální');

    Http::assertSentCount(1);
});

it('ceny stahuje jen hrám, které někdo sleduje', function (): void {
    Game::factory()->create(['itad_id' => CONTROL_RESONANT_ID]);

    $this->get(CRON_URL)->assertSeeText('ceny — ITAD her: 0, gg.deals her: 0');

    expect(CurrentPrice::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'isthereanydeal'));
});

it('výpadek zdroje ohlásí kódem 500, ostatní kroky proběhnou', function (): void {
    watcherOf([BIOMUTANT_ID]);
    fakeApis(['api.isthereanydeal.com/*' => apiError(503)]);

    $this->get(CRON_URL)
        ->assertInternalServerError()
        ->assertSeeText('kurzy ČNB — uložen lístek')
        ->assertSeeText('ceny — ITAD her: chyba, gg.deals her: 1')
        ->assertSeeText('cache — smazáno');
});

it('nečitelný lístek ČNB nezastaví ceny', function (): void {
    watcherOf([BIOMUTANT_ID]);
    fakeApis(['www.cnb.cz/*' => Http::response('<html>Údržba</html>')]);

    $this->get(CRON_URL)
        ->assertInternalServerError()
        ->assertSeeText(__('app.cron.step_failed', ['step' => 'kurzy ČNB']))
        ->assertSeeText('ceny — ITAD her: 1, gg.deals her: 1');
});

it('když ještě běží předchozí volání, nic neudělá', function (): void {
    $lock = Cache::lock('cron:run', 60);
    $lock->get();

    $this->get(CRON_URL)->assertConflict();

    Http::assertNothingSent();
    $lock->release();
});
