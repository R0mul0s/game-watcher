<?php

/**
 * Rozpoznání odkazu na PlayStation Store (R20, SSRF).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationUrl;

it('rozpozná odkaz na produkt v libovolném jazyce obchodu', function (string $url): void {
    $parsed = PlayStationUrl::parse($url);

    expect($parsed?->kind)->toBe(PlayStationUrl::KIND_PRODUCT)
        ->and($parsed?->id)->toBe(PSN_CONTROL_RESONANT);
})->with([
    'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    'https://store.playstation.com/en-us/product/'.PSN_CONTROL_RESONANT.'/',
    'store.playstation.com/zh-hant-tw/product/'.PSN_CONTROL_RESONANT,
    '  http://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT.'?smcid=web  ',
]);

it('rozpozná odkaz na stránku hry (koncept)', function (): void {
    $parsed = PlayStationUrl::parse('https://store.playstation.com/en-gb/concept/10017682?emcid=x');

    expect($parsed?->kind)->toBe(PlayStationUrl::KIND_CONCEPT)
        ->and($parsed?->id)->toBe('10017682');
});

it('odmítne jiný web a jiné stránky obchodu', function (string $url): void {
    expect(PlayStationUrl::parse($url))->toBeNull();
})->with([
    'https://evil.example.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    'https://store.playstation.com.evil.example/cs-cz/product/'.PSN_CONTROL_RESONANT,
    'https://store.playstation.com/cs-cz/category/3f772501-f6f8-49b7-abac-874a88ca4897/1',
    'https://store.playstation.com/cs-cz/product/../../concept/1',
    'https://store.playstation.com/cs-cz/product/neplatne-id',
    'javascript:alert(1)',
    '',
]);

it('rozpozná stránku hry na www.playstation.com', function (): void {
    $parsed = PlayStationUrl::parse('https://www.playstation.com/cs-cz/games/saros/');

    expect($parsed?->kind)->toBe(PlayStationUrl::KIND_GAME_PAGE)
        ->and($parsed?->id)->toBe('saros');
});

it('na www.playstation.com přijme jen stránku hry', function (string $url): void {
    expect(PlayStationUrl::parse($url))->toBeNull();
})->with([
    'https://www.playstation.com/cs-cz/ps-plus/',
    'https://www.playstation.com/cs-cz/games/../../etc/',
    'https://www.playstation.com/cs-cz/games/saros/trailer/',
]);

it('z názvu hry odvodí adresu stránky na www.playstation.com', function (string $title, ?string $slug): void {
    expect(PlayStationUrl::gamePageSlug($title))->toBe($slug);
})->with([
    ['Until Dawn™', 'until-dawn'],
    ['Marvel’s Spider-Man 2', 'marvels-spider-man-2'],
    ["Baldur's Gate 3", 'baldurs-gate-3'],
    ['Ghost of Yōtei', 'ghost-of-yotei'],
    ['Kairos: Until the Dawn Breaks', 'kairos-until-the-dawn-breaks'],
    ['™', null],
]);
