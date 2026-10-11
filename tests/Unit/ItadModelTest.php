<?php

/**
 * Převod odpovědí IsThereAnyDeal na datové třídy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Itad\ItadGame;
use App\Domain\Itad\ItadGameInfo;

it('z výsledku hledání vezme největší banner a menší náhled', function (): void {
    $game = ItadGame::fromApi(jsonFixture('itad/search-control-resonant')[0]);

    expect($game?->id)->toBe(CONTROL_RESONANT_ID)
        ->and($game?->title)->toBe('CONTROL Resonant')
        ->and($game?->type)->toBe('game')
        ->and($game?->bannerUrl)->toContain('/banner600.jpg')
        ->and($game?->thumbnailUrl)->toContain('/banner300.jpg')
        ->and($game?->boxartUrl)->toContain('/boxart.jpg');
});

it('DLC bez obalu má obal prázdný', function (): void {
    $dlc = ItadGame::fromApi(jsonFixture('itad/search-control-resonant')[2]);

    expect($dlc?->type)->toBe('dlc')
        ->and($dlc?->boxartUrl)->toBeNull()
        ->and($dlc?->bannerUrl)->toContain('/banner600.jpg');
});

it('název ořízne o mezery navíc', function (): void {
    expect(ItadGame::fromApi(['id' => BIOMUTANT_ID, 'slug' => 'zamb', 'title' => 'ZAMB! Biomutant Extermination '])?->title)
        ->toBe('ZAMB! Biomutant Extermination');
});

it('neúplný objekt hry odmítne', function (array $data): void {
    expect(ItadGame::fromApi($data))->toBeNull();
})->with([
    'bez ID' => [['slug' => 'x', 'title' => 'X']],
    'bez slugu' => [['id' => BIOMUTANT_ID, 'title' => 'X']],
    'název není text' => [['id' => BIOMUTANT_ID, 'slug' => 'x', 'title' => 42]],
]);

it('přežije cestu přes cache jako pole', function (): void {
    $game = ItadGame::fromApi(jsonFixture('itad/search-control-resonant')[0]);

    expect(ItadGame::fromArray($game?->toArray() ?? []))->toEqual($game);
});

it('z detailu vezme Steam App ID, vydání, vývojáře a hodnocení', function (): void {
    $info = ItadGameInfo::fromApi(jsonFixture('itad/info-control-resonant'));

    expect($info?->steamAppId)->toBe(3669870)
        ->and($info?->releaseDate)->toBe('2026-09-24')
        ->and($info?->developers)->toBe(['Remedy Entertainment'])
        ->and($info?->publishers)->toBe(['Remedy Entertainment', 'GMG Label Limited'])
        ->and($info?->tags)->toContain('Action RPG')
        ->and($info?->reviews[0])->toBe([
            'source' => 'Steam',
            'score' => 90,
            'count' => 8194,
            'url' => 'https://store.steampowered.com/app/3669870/',
        ])
        ->and($info?->url)->toBe('https://isthereanydeal.com/game/control-resonant/');
});

it('hra mimo Steam nemá Steam App ID', function (): void {
    expect(ItadGameInfo::fromApi(jsonFixture('itad/info-alan-wake-2'))?->steamAppId)->toBeNull();
});
