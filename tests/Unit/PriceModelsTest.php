<?php

/**
 * Převod odpovědí cen (ITAD, gg.deals) na datové třídy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\GgDeals\GgDealsPrices;
use App\Domain\Itad\ItadGamePrices;

it('z cen ITAD vezme nabídky v setinách, DRM, konec slevy a historické minimum', function (): void {
    $prices = collect(jsonFixture('itad/prices'))
        ->map(fn (array $game): ?ItadGamePrices => ItadGamePrices::fromApi($game))
        ->keyBy(fn (?ItadGamePrices $prices): string => (string) $prices?->itadId);

    $biomutant = $prices[BIOMUTANT_ID];
    $first = $biomutant->deals[0];
    $dlGamer = collect($prices[CONTROL_RESONANT_ID]->deals)->firstWhere('shopName', 'DLGamer');

    expect($biomutant->deals)->toHaveCount(15)
        ->and($first->shopName)->toBe('WinGameStore')
        ->and($first->shopId)->toBe(64)
        ->and($first->price->cents)->toBe(2496)
        ->and($first->price->currency)->toBe('EUR')
        ->and($first->regular?->cents)->toBe(3566)
        ->and($first->cut)->toBe(30)
        ->and($first->drm)->toBe(['Steam'])
        ->and($first->url)->toStartWith('https://itad.link/')
        ->and($biomutant->historyLow?->cents)->toBe(672)
        ->and($dlGamer?->expiresAt?->toIso8601String())->toBe('2026-10-31T11:00:00+00:00');
});

it('z gg.deals vezme nejnižší cenu obchodů s klíči a historické minimum', function (): void {
    $prices = GgDealsPrices::fromApi(597820, jsonFixture('ggdeals/prices')['data']['597820']);

    expect($prices?->currentKeyshops?->cents)->toBe(541)
        ->and($prices?->historicalKeyshops?->cents)->toBe(524)
        ->and($prices?->currentKeyshops?->currency)->toBe('EUR')
        ->and($prices?->url)->toBe('https://gg.deals/game/biomutant/');
});

it('chybějící cena gg.deals je prázdná, ne nula', function (): void {
    $prices = GgDealsPrices::fromApi(1, ['prices' => ['currentKeyshops' => null, 'historicalKeyshops' => '', 'currency' => 'EUR']]);

    expect($prices?->currentKeyshops)->toBeNull()
        ->and($prices?->historicalKeyshops)->toBeNull();
});
