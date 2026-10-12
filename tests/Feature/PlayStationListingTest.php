<?php

/**
 * Propojení hry s PlayStation Store a jeho ceny (R20).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Enums\Platform;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\StoreListing;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    $this->user = watcherOf([CONTROL_RESONANT_ID]);
    $this->game = Game::query()->sole();
    fakeApis();
});

it('připojí produkt podle odkazu a hned uloží jeho cenu', function (): void {
    $this->actingAs($this->user)
        ->from('/hry/control-resonant')
        ->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/en-us/product/'.PSN_CONTROL_RESONANT])
        ->assertRedirect('/hry/control-resonant')
        ->assertInertiaFlash('success', __('app.listings.connected', ['name' => 'CONTROL Resonant']));

    $listing = StoreListing::query()->sole();
    $price = CurrentPrice::query()->where('shop_key', $listing->shopKey())->sole();

    expect($listing->external_id)->toBe(PSN_CONTROL_RESONANT)
        ->and($listing->platforms)->toBe('PS5')
        // Jazyk z odkazu se zahodí — ceny vždy z české verze
        ->and($listing->url)->toBe('https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT)
        ->and($price->source)->toBe(PriceSource::PlayStation)
        ->and($price->platform)->toBe(Platform::PlayStation)
        ->and($price->amount)->toBe('1419.00')
        ->and($price->currency)->toBe('CZK')
        ->and($price->drm)->toBe('PS5');
});

it('z odkazu na stránku hry připojí její výchozí produkt', function (): void {
    $this->actingAs($this->user)
        ->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/concept/'.PSN_CONTROL_RESONANT_CONCEPT]);

    expect(StoreListing::query()->sole()->external_id)->toBe(PSN_CONTROL_RESONANT);
});

it('stejný produkt podruhé nezdvojí', function (): void {
    $url = 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT;

    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => $url]);
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => $url]);

    expect(StoreListing::query()->count())->toBe(1);
});

it('odkaz mimo PlayStation Store odmítne a nikam nevolá', function (): void {
    $this->actingAs($this->user)
        ->post('/hry/control-resonant/obchody', ['url' => 'https://evil.example.com/cs-cz/product/'.PSN_CONTROL_RESONANT])
        ->assertSessionHasErrors(['url' => __('app.listings.not_playstation')]);

    Http::assertNothingSent();
    expect(StoreListing::query()->count())->toBe(0);
});

it('neexistující produkt odmítne', function (): void {
    $this->actingAs($this->user)
        ->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/EP0000-PPSA00000_00-0000000000000000'])
        ->assertSessionHasErrors('url');

    expect(StoreListing::query()->count())->toBe(0);
});

it('výpadek PlayStation Store ohlásí a nic neuloží', function (): void {
    fakeApis(['store.playstation.com/*' => Http::response('Access Denied', 403)]);

    $this->actingAs($this->user)
        ->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT])
        ->assertInertiaFlash('error', __('app.listings.unavailable'));

    expect(StoreListing::query()->count())->toBe(0);
});

it('PlayStation je ve výpisu cen detailu jako samostatná položka s platformou a edicí', function (): void {
    fakeApis(['store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME => Http::response(psnFixture('product-kingdom-come'))]);
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME]);

    $this->actingAs($this->user)
        ->get('/hry/control-resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->has('game.prices.offers', 13)
            // 139,80 Kč — nejlevnější ze všech, i před obchody pro PC
            ->where('game.prices.offers.0.shop', 'PlayStation Store')
            ->where('game.prices.offers.0.platform', 'playstation')
            ->where('game.prices.offers.0.edition', 'Kingdom Come: Deliverance')
            ->where('game.prices.offers.0.price', ['amount' => 139.8, 'currency' => 'CZK'])
            ->where('game.prices.offers.0.cut', 80)
            ->where('game.prices.offers.0.drm', 'PS4, PS5')
            ->where('game.prices.offers.0.memberIncluded', true)
            ->where('game.prices.offers.1.platform', 'pc')
            ->has('game.listings', 1)
            ->where('game.listings.0.name', 'Kingdom Come: Deliverance'));
});

it('u ceny s PS Plus ukáže obě ceny', function (): void {
    fakeApis(['store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US => Http::response(psnFixture('product-kcd2-expansion-us'))]);
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US]);

    $this->actingAs($this->user)
        ->get('/hry/control-resonant')
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.prices.offers.0.shop', 'PlayStation Store')
            ->where('game.prices.offers.0.original', ['amount' => 19.49, 'currency' => 'USD'])
            ->where('game.prices.offers.0.member.original', ['amount' => 17.99, 'currency' => 'USD'])
            ->where('game.prices.offers.0.memberIncluded', false));
});

it('cron obnoví cenu připojeného produktu', function (): void {
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT]);
    CurrentPrice::query()->where('source', PriceSource::PlayStation->value)->update(['amount' => '1.00']);
    fakeApis();

    $result = app(RefreshPrices::class)->watched();

    expect($result->updatedBy(PriceSource::PlayStation))->toBe(1)
        ->and(CurrentPrice::query()->where('source', PriceSource::PlayStation->value)->sole()->amount)->toBe('1419.00');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT
        && str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla'));
});

it('produkt, který z obchodu zmizel, ztratí cenu, ale propojení zůstane', function (): void {
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT]);
    fakeApis(['store.playstation.com/*' => Http::response('Not Found', 404)]);

    app(RefreshPrices::class)->watched();

    expect(CurrentPrice::query()->where('source', PriceSource::PlayStation->value)->exists())->toBeFalse()
        ->and(StoreListing::query()->count())->toBe(1);
});

it('výpadek PlayStation Store v cronu ostatní zdroje nezastaví', function (): void {
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT]);
    fakeApis(['store.playstation.com/*' => Http::response('Access Denied', 403)]);

    $result = app(RefreshPrices::class)->watched();

    expect($result->failed)->toBe([PriceSource::PlayStation])
        ->and($result->updatedBy(PriceSource::Itad))->toBe(1)
        // Dosavadní cena zůstává
        ->and(CurrentPrice::query()->where('source', PriceSource::PlayStation->value)->exists())->toBeTrue();
});

it('odpojení smaže cenu i historii produktu', function (): void {
    $this->actingAs($this->user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT]);
    $listing = StoreListing::query()->sole();

    $this->actingAs($this->user)
        ->delete("/hry/control-resonant/obchody/{$listing->id}")
        ->assertInertiaFlash('success', __('app.listings.disconnected', ['name' => 'CONTROL Resonant']));

    expect(StoreListing::query()->count())->toBe(0)
        ->and(CurrentPrice::query()->where('shop_key', $listing->shopKey())->exists())->toBeFalse()
        ->and(DB::table('price_history')->where('shop_key', $listing->shopKey())->exists())->toBeFalse();
});

it('propojení jiné hry přes adresu této hry neodpojí', function (): void {
    $other = Game::factory()->create();
    $listing = StoreListing::query()->create([
        'game_id' => $other->id,
        'source' => PriceSource::PlayStation->value,
        'external_id' => PSN_CONTROL_RESONANT,
        'name' => 'Cizí',
        'url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    ]);

    $this->actingAs($this->user)->delete("/hry/control-resonant/obchody/{$listing->id}")->assertNotFound();

    expect(StoreListing::query()->count())->toBe(1);
});
