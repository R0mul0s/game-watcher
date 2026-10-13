<?php

/**
 * Žánry a režimy ze Steamu, počet hráčů z PS Store a filtr hráčů (R25),
 * čeština ze Steamu i PS Store (R47).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\PlayStation\PlayStationPage;
use App\Domain\PlayStation\PlayStationProduct;
use App\Domain\Steam\SteamAppDetails;
use App\Enums\CzechSupport;
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

it('ze Steamu pozná český dabing, titulky i hru bez češtiny (R47)', function (): void {
    expect(steamDetails('kingdom-come-2', KINGDOM_COME_2_STEAM_ID)->czech)->toBe(CzechSupport::Voice)
        ->and(steamDetails('cyberpunk-2077', CYBERPUNK_STEAM_ID)->czech)->toBe(CzechSupport::Text)
        ->and(steamDetails('it-takes-two', IT_TAKES_TWO_STEAM_ID)->czech)->toBe(CzechSupport::None)
        // Bez seznamu jazyků je čeština neznámá, ne „žádná“
        ->and(SteamAppDetails::fromApi(['genres' => []])->czech)->toBeNull();
});

it('z PS Store pozná český dabing a produkt bez jazyků nechá neznámý (R47)', function (): void {
    $product = fn (string $fixture, string $id): ?PlayStationProduct => PlayStationPage::fromHtml(psnFixture($fixture))->product($id);

    expect($product('product-kingdom-come', PSN_KINGDOM_COME)?->czech)->toBe(CzechSupport::Voice)
        ->and($product('product-it-takes-two', PSN_IT_TAKES_TWO)?->czech)->toBe(CzechSupport::None)
        // DLC z amerického obchodu má prázdné seznamy jazyků
        ->and($product('product-kcd2-expansion-us', PSN_KCD2_EXPANSION_US)?->czech)->toBeNull();
});

it('filtr češtiny chce aspoň zvolenou úroveň a neznámou nepustí (R47)', function (CzechSupport $filter, ?CzechSupport $game, bool $matches): void {
    expect($filter->matches($game))->toBe($matches);
})->with([
    'titulky u titulků' => [CzechSupport::Text, CzechSupport::Text, true],
    'titulky u dabingu' => [CzechSupport::Text, CzechSupport::Voice, true],
    'dabing u titulků' => [CzechSupport::Voice, CzechSupport::Text, false],
    'titulky bez češtiny' => [CzechSupport::Text, CzechSupport::None, false],
    'titulky u neznámé' => [CzechSupport::Text, null, false],
]);

it('z několika zdrojů vezme nejlepší češtinu (R47)', function (): void {
    expect(CzechSupport::best(CzechSupport::None, null, CzechSupport::Voice, CzechSupport::Text))->toBe(CzechSupport::Voice)
        ->and(CzechSupport::best(null, CzechSupport::None))->toBe(CzechSupport::None)
        ->and(CzechSupport::best(null, null))->toBeNull()
        ->and(CzechSupport::best())->toBeNull();
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
