<?php

/**
 * Rozbor stránky PlayStation Store (R20).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationPage;

it('přečte název, platformy a běžnou cenu produktu bez slevy', function (): void {
    $product = PlayStationPage::fromHtml(psnFixture('product-control-resonant'))->product(PSN_CONTROL_RESONANT);

    expect($product?->name)->toBe('CONTROL Resonant')
        ->and($product?->platforms)->toBe(['PS5'])
        ->and($product?->price?->cents)->toBe(141900)
        ->and($product?->price?->currency)->toBe('CZK')
        ->and($product?->cut())->toBe(0)
        ->and($product?->saleEndsAt)->toBeNull()
        ->and($product?->plusPrice)->toBeNull()
        ->and($product?->inPlusCatalog)->toBeFalse();
});

it('přečte slevu, její konec a zařazení do katalogu PS Plus', function (): void {
    $product = PlayStationPage::fromHtml(psnFixture('product-kingdom-come'))->product(PSN_KINGDOM_COME);

    expect($product?->price?->cents)->toBe(13980)
        ->and($product?->basePrice?->cents)->toBe(69900)
        ->and($product?->cut())->toBe(80)
        ->and($product?->saleEndsAt?->toIso8601String())->toBe('2026-10-21T22:59:00+00:00')
        ->and($product?->platforms)->toBe(['PS4', 'PS5'])
        ->and($product?->inPlusCatalog)->toBeTrue()
        // Hra v katalogu není cena s PS Plus — zdarma je jen v předplatném Extra
        ->and($product?->plusPrice)->toBeNull();
});

it('přečte cenu s PS Plus vedle běžné ceny', function (): void {
    $product = PlayStationPage::fromHtml(psnFixture('product-kcd2-expansion-us'))->product(PSN_KCD2_EXPANSION_US);

    expect($product?->price?->cents)->toBe(1949)
        ->and($product?->plusPrice?->cents)->toBe(1799)
        ->and($product?->plusPrice?->currency)->toBe('USD')
        ->and($product?->cut())->toBe(35);
});

it('vrátí výchozí produkt stránky hry', function (): void {
    expect(PlayStationPage::fromHtml(psnFixture('concept-control-resonant'))->defaultProductId(PSN_CONTROL_RESONANT_CONCEPT))
        ->toBe(PSN_CONTROL_RESONANT);
});

it('na stránce bez dat nic nevymyslí', function (): void {
    $page = PlayStationPage::fromHtml('<html><body>Access Denied</body></html>');

    expect($page->product(PSN_CONTROL_RESONANT))->toBeNull()
        ->and($page->defaultProductId(PSN_CONTROL_RESONANT_CONCEPT))->toBeNull();
});
