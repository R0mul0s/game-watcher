<?php

/**
 * Krabicové hry na PS5 — odkaz od uživatele a rozbor schema.org (R52).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Retail\RetailUrl;
use App\Domain\Retail\SchemaProductPage;
use App\Enums\PriceSource;

it('rozpozná odkaz na produkt Xzone a Heureky a zahodí dotaz', function (string $input, PriceSource $source, string $url): void {
    $parsed = RetailUrl::parse($input);

    expect($parsed?->source)->toBe($source)
        ->and($parsed?->url)->toBe($url);
})->with([
    'Xzone' => ['https://www.xzone.cz/ghost-of-yotei-complete-edition-ps5?utm_source=x#recenze', PriceSource::Xzone, XZONE_GHOST_OF_YOTEI_URL],
    'Xzone bez www a https' => ['xzone.cz/ghost-of-yotei-complete-edition-ps5', PriceSource::Xzone, XZONE_GHOST_OF_YOTEI_URL],
    'Heureka' => ['https://hry-na-ps5.heureka.cz/astro-bot/?v=1', PriceSource::Heureka, HEUREKA_ASTRO_BOT_URL],
    'Heureka bez lomítka' => ['hry-na-ps5.heureka.cz/astro-bot', PriceSource::Heureka, HEUREKA_ASTRO_BOT_URL],
]);

it('jiného hostitele ani tvar adresy nepustí (SSRF)', function (string $input): void {
    expect(RetailUrl::parse($input))->toBeNull();
})->with([
    'cizí web' => ['https://evil.example/www.xzone.cz/hra'],
    'podvržená doména' => ['https://hry-na-ps5.heureka.cz.evil.example/astro-bot/'],
    'hlavní stránka Heureky' => ['https://www.heureka.cz/astro-bot/'],
    'víc úrovní poddomény' => ['https://a.b.heureka.cz/astro-bot/'],
    'kategorie Heureky' => ['https://hry-na-ps5.heureka.cz/'],
    'vnořená cesta Xzone' => ['https://www.xzone.cz/hry/ps5/novinky'],
    'nepodporovaný obchod' => ['https://alza.cz/astro-bot'],
]);

it('z Xzone přečte produkt, EAN a jedinou nabídku skladem', function (): void {
    $product = SchemaProductPage::product(retailFixture('xzone-ghost-of-yotei'));

    expect($product?->name)->toContain('Ghost of Yotei')
        ->and($product?->gtin)->toBe('0711719073543')
        ->and($product?->offers)->toHaveCount(1)
        ->and($product?->bestOffer()?->price->cents)->toBe(189900)
        ->and($product?->bestOffer()?->seller)->toBeNull();
});

it('z Heureky vezme nejlevnější nový kus skladem z českého obchodu', function (): void {
    $product = SchemaProductPage::product(retailFixture('heureka-astro-bot'));
    $best = $product?->bestOffer();

    // 10 nabídek: nejlevnější Allegro.cz 1 108 Kč; Digihry.sk se nepočítá, Alza je vyprodaná
    expect($product?->name)->toBe('Astro Bot')
        ->and($product?->offers)->toHaveCount(10)
        ->and($best?->seller)->toBe('Allegro.cz')
        ->and($best?->price->cents)->toBe(110800);

    $withoutAllegro = SchemaProductPage::product(str_replace('"Allegro.cz"', '"Allegro.sk"', retailFixture('heureka-astro-bot')));
    // Allegro „ze Slovenska“ i Digihry.sk (1 142,86 Kč) se nepočítají — nejlevnější je Prokonzole.cz
    expect($withoutAllegro?->bestOffer()?->seller)->toBe('Prokonzole.cz')
        ->and($withoutAllegro?->bestOffer()?->price->cents)->toBe(118700);
});

it('bez nabídky skladem nemá produkt cenu a stránka bez produktu nic nevymyslí', function (): void {
    $soldOut = SchemaProductPage::product(str_replace('InStock', 'OutOfStock', retailFixture('xzone-ghost-of-yotei')));

    expect($soldOut?->offers)->toHaveCount(1)
        ->and($soldOut?->bestOffer())->toBeNull()
        ->and(SchemaProductPage::product('<html><script type="application/ld+json">{"@type":"WebPage"}</script></html>'))->toBeNull()
        ->and(SchemaProductPage::product('<html><script type="application/ld+json">{nevalidní</script></html>'))->toBeNull();
});
