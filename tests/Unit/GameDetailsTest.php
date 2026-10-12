<?php

/**
 * Žánry a režimy ze Steamu, počet hráčů z PS Store a filtr hráčů (R25).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationPage;
use App\Domain\Steam\SteamAppDetails;
use App\Enums\GameMode;
use App\Enums\PlayerFilter;

/**
 * Údaje appdetails ze zachycené odpovědi.
 */
function steamDetails(string $name, int $appId): SteamAppDetails
{
    return SteamAppDetails::fromApi(jsonFixture('steam/appdetails-'.$name)[$appId]['data']);
}

it('ze Steamu vezme české žánry a z kategorií jen režimy hry', function (): void {
    $itTakesTwo = steamDetails('it-takes-two', IT_TAKES_TWO_STEAM_ID);
    $biomutant = steamDetails('biomutant', 597820);

    expect($itTakesTwo->genres)->toBe(['Akční', 'Dobrodružné'])
        ->and($itTakesTwo->modes)->toBe([GameMode::Multi, GameMode::Coop, GameMode::OnlineCoop, GameMode::LocalCoop, GameMode::LocalMulti])
        ->and($biomutant->genres)->toBe(['Akční', 'RPG'])
        ->and($biomutant->modes)->toBe([GameMode::Single]);
});

it('z PS Store vezme počet hráčů u konzole a online', function (): void {
    $itTakesTwo = PlayStationPage::fromHtml(psnFixture('product-it-takes-two'))->product(PSN_IT_TAKES_TWO);
    $control = PlayStationPage::fromHtml(psnFixture('product-control-resonant'))->product(PSN_CONTROL_RESONANT);

    expect($itTakesTwo?->localPlayers)->toBe(2)
        ->and($itTakesTwo?->onlinePlayers)->toBe(2)
        ->and($control?->localPlayers)->toBe(1)
        ->and($control?->onlinePlayers)->toBeNull();
});

it('filtr hráčů pozná hru pro jednoho, pro dva a couch co-op', function (PlayerFilter $filter, array $modes, bool $matches): void {
    expect($filter->matches($modes))->toBe($matches);
})->with([
    'pro jednoho' => [PlayerFilter::Solo, [GameMode::Single], true],
    'pro jednoho u co-op hry' => [PlayerFilter::Solo, [GameMode::Coop], false],
    'pro dva online co-op' => [PlayerFilter::Duo, [GameMode::OnlineCoop], true],
    'pro dva u singleplayeru' => [PlayerFilter::Duo, [GameMode::Single], false],
    'couch co-op' => [PlayerFilter::Couch, [GameMode::Single, GameMode::LocalCoop], true],
    'couch u online co-op' => [PlayerFilter::Couch, [GameMode::OnlineCoop], false],
    'bez údajů' => [PlayerFilter::Couch, [], false],
]);
