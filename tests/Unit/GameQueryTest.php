<?php

/**
 * Rozpoznání dotazu při hledání hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Catalog\GameQuery;

it('prázdný dotaz nehledá', function (string $input): void {
    expect(GameQuery::parse($input))->toBeNull();
})->with(['', '   ', "\n\t"]);

it('text hledá podle názvu a sloučí mezery', function (): void {
    $query = GameQuery::parse("  The Blood   of\tDawnwalker ");

    expect($query?->title)->toBe('The Blood of Dawnwalker')
        ->and($query?->steamAppId)->toBeNull();
});

it('číslo bere jako Steam App ID', function (): void {
    $query = GameQuery::parse(' 597820 ');

    expect($query?->steamAppId)->toBe(597820)
        ->and($query?->title)->toBeNull();
});

it('z odkazu na Steam vezme App ID', function (string $url, int $appId): void {
    expect(GameQuery::parse($url)?->steamAppId)->toBe($appId);
})->with([
    ['https://store.steampowered.com/app/3669870/CONTROL_Resonant/', 3669870],
    ['store.steampowered.com/app/597820', 597820],
    ['https://steamcommunity.com/app/3751260', 3751260],
]);

it('název s číslem není App ID', function (): void {
    expect(GameQuery::parse('Cyberpunk 2077')?->title)->toBe('Cyberpunk 2077');
});

it('stejný název s jinou velikostí písmen má stejný klíč cache', function (): void {
    expect(GameQuery::parse('Biomutant')?->cacheKey())->toBe(GameQuery::parse('BIOMUTANT')?->cacheKey())
        ->and(GameQuery::parse('597820')?->cacheKey())->not->toBe(GameQuery::parse('BIOMUTANT')?->cacheKey());
});
