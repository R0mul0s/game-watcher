<?php

/**
 * Krabicové hry na PS5 z Xzone a Heureky — připojení odkazem a ceny (R52).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\StoreListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

const RETAIL_CRON_URL = '/cron/ceny?token=test-cron-token';

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    $this->user = watcherOf([CONTROL_RESONANT_ID]);
    $this->game = Game::query()->sole();
});

/**
 * Připojí produkt odkazem přes formulář na detailu hry.
 */
function connectRetail(string $url): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/hry/control-resonant')
        ->post('/hry/control-resonant/obchody', ['url' => $url]);
}

it('připojí krabicovku z Xzone a hned uloží cenu pro PlayStation', function (): void {
    connectRetail('https://www.xzone.cz/ghost-of-yotei-complete-edition-ps5?utm_source=x')
        ->assertRedirect('/hry/control-resonant')
        ->assertSessionHasNoErrors();

    $listing = StoreListing::query()->sole();
    $price = CurrentPrice::query()->where('source', PriceSource::Xzone->value)->sole();

    expect($listing)
        ->source->toBe(PriceSource::Xzone)
        ->url->toBe(XZONE_GHOST_OF_YOTEI_URL)
        ->and($price)
        ->shop_key->toBe('xzone-'.$listing->id)
        ->shop_name->toBe('Xzone')
        ->amount->toBe('1899.00')
        ->currency->toBe('CZK')
        ->drm->toBe('PS5 krabice')
        ->and($price->platform->value)->toBe('playstation');
});

it('z Heureky uloží nejlevnější nový kus skladem s obchodem', function (): void {
    connectRetail('hry-na-ps5.heureka.cz/astro-bot')->assertSessionHasNoErrors();

    expect(CurrentPrice::query()->where('source', PriceSource::Heureka->value)->sole())
        ->shop_name->toBe('Allegro.cz (Heureka)')
        ->amount->toBe('1108.00');

    // Na detailu je propojení s obchodem a cena mezi nabídkami pro PlayStation
    $this->actingAs($this->user)->get('/hry/control-resonant')->assertInertia(fn (Assert $page) => $page
        ->where('game.listings.0.shop', 'Heureka')
        ->where('game.listings.0.url', HEUREKA_ASTRO_BOT_URL));
});

it('odkaz na kategorii nebo neexistující produkt odmítne chybou formuláře', function (): void {
    connectRetail('https://www.xzone.cz/neexistujici-hra-ps5')
        ->assertSessionHasErrors(['url' => __('app.listings.not_retail')]);

    // Stránka bez produktu (výpis kategorie) — při připojování chyba formuláře, ne výpadek
    fakeApis([HEUREKA_ASTRO_BOT_URL => Http::response('<html><script type="application/ld+json">{"@type":"WebPage"}</script></html>')]);
    connectRetail(HEUREKA_ASTRO_BOT_URL)->assertSessionHasErrors(['url' => __('app.listings.not_retail')]);

    expect(StoreListing::query()->count())->toBe(0);
});

it('cron obnoví cenu, vyprodaný produkt cenu ztratí a propojení zůstane', function (): void {
    connectRetail(XZONE_GHOST_OF_YOTEI_URL);
    CurrentPrice::query()->where('source', PriceSource::Xzone->value)->update(['amount' => '1.00']);

    expect(app(RefreshPrices::class)->watched()->updatedBy(PriceSource::Xzone))->toBe(1)
        ->and(CurrentPrice::query()->where('source', PriceSource::Xzone->value)->sole()->amount)->toBe('1899.00');

    fakeApis([XZONE_GHOST_OF_YOTEI_URL => Http::response(str_replace('InStock', 'OutOfStock', retailFixture('xzone-ghost-of-yotei')))]);
    app(RefreshPrices::class)->watched();

    expect(CurrentPrice::query()->where('source', PriceSource::Xzone->value)->count())->toBe(0)
        ->and(StoreListing::query()->count())->toBe(1);
});

it('blokace Heureky cenu nesmaže a běh cronu neoznačí, výpadek Xzone ano', function (): void {
    connectRetail(XZONE_GHOST_OF_YOTEI_URL);
    connectRetail(HEUREKA_ASTRO_BOT_URL);
    fakeApis([HEUREKA_ASTRO_BOT_URL => Http::response('<html><title>Just a moment...</title></html>', 403)]);

    $this->get(RETAIL_CRON_URL)
        ->assertOk()
        ->assertSeeText('Xzone: 1, Heureka: chyba');
    expect(CurrentPrice::query()->where('source', PriceSource::Heureka->value)->count())->toBe(1);

    fakeApis([XZONE_GHOST_OF_YOTEI_URL => Http::response('<html>Údržba</html>')]);
    $this->get(RETAIL_CRON_URL)->assertInternalServerError();
    expect(CurrentPrice::query()->where('source', PriceSource::Xzone->value)->sole()->amount)->toBe('1899.00');
});

it('odpojení smaže cenu i historii krabicovky', function (): void {
    connectRetail(XZONE_GHOST_OF_YOTEI_URL);
    $listing = StoreListing::query()->sole();

    $this->actingAs($this->user)->delete('/hry/control-resonant/obchody/'.$listing->id)->assertRedirect();

    expect(CurrentPrice::query()->where('shop_key', $listing->shopKey())->count())->toBe(0)
        ->and(DB::table('price_history')->where('shop_key', $listing->shopKey())->count())->toBe(0);
});
