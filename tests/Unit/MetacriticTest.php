<?php

/**
 * Odkaz na Metacritic a rozbor stránky hry (R33).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Metacritic\MetacriticGame;
use App\Domain\Metacritic\MetacriticUrl;

it('z odkazu vezme slug hry, i s podstránkou a bez schématu', function (string $url, ?string $slug): void {
    expect(MetacriticUrl::parse($url))->toBe($slug);
})->with([
    ['https://www.metacritic.com/game/saros/', 'saros'],
    ['www.metacritic.com/game/alan-wake-ii/critic-reviews/?platform=pc', 'alan-wake-ii'],
    ['https://WWW.METACRITIC.COM/game/Saros', 'saros'],
    ['https://metacritic.com/game/saros/', 'saros'],
    ['https://evil.metacritic.com.example/game/saros/', null],
    ['https://www.metacritic.com/movie/saros/', null],
    ['https://www.metacritic.com/', null],
]);

it('slug odvodí z názvu bez ochranných známek a apostrofů', function (string $title, ?string $slug): void {
    expect(MetacriticUrl::slugFromTitle($title))->toBe($slug);
})->with([
    ['SAROS', 'saros'],
    ['Ghost of Yōtei', 'ghost-of-yotei'],
    ['Marvel’s Spider-Man 2', 'marvels-spider-man-2'],
    ['Until Dawn™', 'until-dawn'],
    ['™', null],
]);

it('ze strukturovaných dat přečte název, Metascore a platformy', function (): void {
    $game = MetacriticGame::fromHtml(metacriticFixture('saros'));

    expect($game)->not->toBeNull()
        ->name->toBe('SAROS')
        ->score->toBe(87)
        ->reviews->toBe(143)
        ->platforms->toBe(['PlayStation 5']);
});

it('nehodnocená hra nemá Metascore, stránka bez dat hry nic nevrátí', function (): void {
    expect(MetacriticGame::fromHtml(metacriticFixture('intergalactic-the-heretic-prophet'))?->score)->toBeNull()
        ->and(MetacriticGame::fromHtml('<html><head><title>Metacritic</title></head></html>'))->toBeNull();
});
