<?php

/**
 * Exkluzivity PlayStationu — hry, které IsThereAnyDeal zná jen jako zástupný záznam (R22).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationPage;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\StoreListing;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
});

it('najde hru podle přesného názvu, i když ji běžné hledání ITAD vynechá', function (): void {
    $this->actingAs(user())
        ->get('/hry/pridat?q=Saros')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.itadId', SAROS_ID)
            ->where('results.0.title', 'SAROS')
            // ITAD u zástupného záznamu druh neuvádí — bere se jako hra
            ->where('results.0.type', 'game')
            ->where('results.0.thumbnailUrl', null)
            // Za ní výsledky běžného hledání
            ->where('results.1.itadId', CONTROL_RESONANT_ID));
});

it('hra z přesného názvu, která je i v běžném hledání, není ve výsledcích dvakrát', function (): void {
    fakeApis(['api.isthereanydeal.com/games/lookup/v1?title=Saros' => Http::response(jsonFixture('itad/lookup-biomutant'))]);

    $this->actingAs(user())
        ->get('/hry/pridat?q=Saros')
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.itadId', BIOMUTANT_ID)
            ->has('results', 4));
});

it('PS Store u exkluzivity nepočítá zkušební verzi PS Plus Premium jako cenu ani katalog', function (): void {
    $product = PlayStationPage::fromHtml(psnFixture('product-saros'))->product(PSN_SAROS);

    expect($product?->price?->cents)->toBe(142425)
        ->and($product?->basePrice?->cents)->toBe(189900)
        ->and($product?->cut())->toBe(25)
        ->and($product?->plusPrice)->toBeNull()
        ->and($product?->inPlusCatalog)->toBeFalse()
        ->and($product?->releaseDate)->toBe('2026-04-30')
        ->and($product?->publisher)->toBe('Sony Interactive Entertainment')
        ->and($product?->coverUrl)->toStartWith('https://image.api.playstation.com/')->toEndWith('?w=400')
        ->and($product?->bannerUrl)->toEndWith('?w=600');
});

it('připojení PS Store doplní exkluzivitě obrázky, datum vydání a vydavatele', function (): void {
    $user = watcherOf([SAROS_ID]);
    $game = Game::query()->sole();
    expect($game->boxart_url)->toBeNull()
        ->and(CurrentPrice::query()->count())->toBe(0);

    $this->actingAs($user)->post('/hry/saros/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_SAROS]);

    $game->refresh();
    expect($game->boxart_url)->toEndWith('?w=400')
        ->and($game->banner_url)->toEndWith('?w=600')
        ->and($game->release_date?->toDateString())->toBe('2026-04-30')
        ->and($game->publishers)->toBe(['Sony Interactive Entertainment'])
        ->and(CurrentPrice::query()->sole()->amount)->toBe('1424.25');
});

it('údaje z ITAD připojení PS Store nepřepíše', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID]);
    $game = Game::query()->sole();
    $boxart = $game->boxart_url;

    $this->actingAs($user)->post('/hry/control-resonant/obchody', ['url' => 'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT]);

    expect($game->refresh()->boxart_url)->toBe($boxart)
        ->and($game->publishers)->toBe(['Remedy Entertainment', 'GMG Label Limited']);
});

it('přijme odkaz na stránku hry z www.playstation.com a připojí její standardní edici', function (): void {
    $user = watcherOf([SAROS_ID]);

    $this->actingAs($user)
        ->post('/hry/saros/obchody', ['url' => 'https://www.playstation.com/cs-cz/games/saros/'])
        ->assertSessionHasNoErrors();

    expect(StoreListing::query()->sole()->external_id)->toBe(PSN_SAROS);
});

it('stránka hry bez odkazu do obchodu skončí srozumitelnou chybou', function (): void {
    $user = watcherOf([SAROS_ID]);
    fakeApis(['www.playstation.com/cs-cz/games/ghost-of-yotei/' => Http::response('<html><body>Ghost of Yōtei</body></html>')]);

    $this->actingAs($user)
        ->post('/hry/saros/obchody', ['url' => 'https://www.playstation.com/en-gb/games/ghost-of-yotei/'])
        ->assertSessionHasErrors(['url' => __('app.listings.not_playstation')]);
});
