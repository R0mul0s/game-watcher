<?php

/**
 * Hlídaná platforma ve filtru cen a název produktu PS Store pro hledání (R24).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationProduct;
use App\Domain\Pricing\PriceFilter;
use App\Enums\Platform;

/**
 * Produkt PS Store jen s názvem.
 */
function productNamed(string $name): PlayStationProduct
{
    return new PlayStationProduct('EP0000-PPSA00000_00-TEST000000000000', $name, ['PS5'], null, null, null, null, false);
}

it('z názvu produktu pro hledání vynechá ochranné známky a označení platformy', function (string $name, string $title): void {
    expect(productNamed($name)->searchTitle())->toBe($title);
})->with([
    ['Until Dawn™', 'Until Dawn'],
    ['Marvel’s Spider-Man 2', 'Marvel’s Spider-Man 2'],
    ['Hogwarts Legacy PS4 & PS5', 'Hogwarts Legacy'],
    ['Cyberpunk 2077 (PS5)', 'Cyberpunk 2077'],
    ['Ghost of Yōtei', 'Ghost of Yōtei'],
    ['™', '™'],
]);

it('filtr cen hry hlídané jen na jedné platformě bere jen tu platformu', function (): void {
    $all = new PriceFilter(null, true);
    $restricted = $all->restrictedTo(Platform::PlayStation);

    expect($all->restrictedTo(null))->toBe($all)
        ->and($restricted->platform)->toBe(Platform::PlayStation)
        ->and($restricted->withPlus)->toBeTrue()
        ->and($restricted->accepts(Platform::Pc))->toBeFalse()
        ->and($restricted->acceptsKeyshops())->toBeFalse();
});

it('platforma z filtru přehledu má přednost před hlídanou', function (): void {
    $filter = new PriceFilter(Platform::Pc);

    expect($filter->restrictedTo(Platform::PlayStation))->toBe($filter);
});
