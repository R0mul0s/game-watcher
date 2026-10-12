<?php

/**
 * Konfigurace Pestu a sdílené pomocné funkce testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Watchlist\Actions\WatchGame;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Uložený účet s heslem UserFactory::PASSWORD.
 *
 * @param  array<string, mixed>  $attributes
 */
function user(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/** ITAD ID her ze zachycených odpovědí (tests/Fixtures/itad/). */
const CONTROL_RESONANT_ID = '019b1065-1cb5-734b-86a1-a67493dbc0c4';
const BIOMUTANT_ID = '018d937f-2e7e-7025-89c4-5e9e7becf198';
const ALAN_WAKE_2_ID = '018d937f-7598-73d1-9435-6d3358dd5f98';
/** Exkluzivita PS5 — v ITAD jen zástupný záznam bez druhu, obrázků a obchodů (R22). */
const SAROS_ID = '019e509d-2701-7364-a18e-ea16dd3f5aa4';

/** Produkty PlayStation Store ze zachycených stránek (tests/Fixtures/playstation/). */
const PSN_CONTROL_RESONANT = 'EP5291-PPSA34547_00-0210080241603648';
const PSN_CONTROL_RESONANT_CONCEPT = '10017682';
const PSN_KINGDOM_COME = 'EP4062-PPSA28731_00-0405812491993057';
const PSN_KCD2_EXPANSION_US = 'UP2047-PPSA21705_00-KINGDOM2EXPASIEA';
const PSN_SAROS = 'EP9000-PPSA07631_00-SAROS00000000000';
const PSN_UNTIL_DAWN = 'EP9000-PPSA15421_00-FULLGAME00000001';
const PSN_UNTIL_DAWN_CONCEPT = '10007897';
const PSN_IT_TAKES_TWO = 'EP0006-PPSA02343_00-ITTAKESTWORETAIL';

// Steam App ID se zachycenými údaji appdetails (tests/Fixtures/steam)
const IT_TAKES_TWO_STEAM_ID = 1426210;
const STEAM_DETAILS_FIXTURES = [
    597820 => 'biomutant',
    3669870 => 'control-resonant',
    IT_TAKES_TWO_STEAM_ID => 'it-takes-two',
];

/**
 * Zkrácená stránka PlayStation Store zachycená 12. 10. 2026.
 */
function psnFixture(string $name): string
{
    return (string) file_get_contents(fixture("playstation/{$name}.html"));
}

/**
 * Skutečná odpověď API zachycená 11. 10. 2026 (bez klíče) — cestu vrací
 * fixture() z Pestu (tests/Fixtures/<cesta>.json).
 *
 * @return array<mixed>
 */
function jsonFixture(string $path): array
{
    return json_decode((string) file_get_contents(fixture("{$path}.json")), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Podvrhne všechna externí API ze zachycených odpovědí — IsThereAnyDeal
 * (hledání, lookup, detail, ceny), gg.deals a kurzy ČNB. Neznámé ITAD ID
 * vrátí 404 jako skutečné API.
 *
 * Volá se i opakovaně: nahradí dřívější podvrhy — další Http::fake() by se jen
 * přidal za ně a vyhrála by první shoda. $overrides mají přednost (výpadek
 * jednoho zdroje: apiError()).
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeApis(array $overrides = []): void
{
    $defaults = [
        'api.isthereanydeal.com/games/search/v1*' => Http::response(jsonFixture('itad/search-control-resonant')),
        'api.isthereanydeal.com/games/lookup/v1?appid=597820' => Http::response(jsonFixture('itad/lookup-biomutant')),
        'api.isthereanydeal.com/games/lookup/v1?title=Saros' => Http::response(jsonFixture('itad/lookup-saros')),
        'api.isthereanydeal.com/games/info/v2?id='.SAROS_ID => Http::response(jsonFixture('itad/info-saros')),
        'api.isthereanydeal.com/games/lookup/v1*' => Http::response(jsonFixture('itad/lookup-not-found')),
        'api.isthereanydeal.com/games/info/v2?id='.CONTROL_RESONANT_ID => Http::response(jsonFixture('itad/info-control-resonant')),
        'api.isthereanydeal.com/games/info/v2?id='.BIOMUTANT_ID => Http::response(jsonFixture('itad/info-biomutant')),
        'api.isthereanydeal.com/games/info/v2?id='.ALAN_WAKE_2_ID => Http::response(jsonFixture('itad/info-alan-wake-2')),
        'api.isthereanydeal.com/games/info/v2*' => apiError(404, 'Not Found'),
        // Zachycené ceny tří her — vrátí jen hry, na které se požadavek ptá, jako skutečné API
        'api.isthereanydeal.com/games/prices/v3*' => fn (Request $request): PromiseInterface => Http::response(array_values(array_filter(
            jsonFixture('itad/prices'),
            fn (array $game): bool => in_array($game['id'], $request->data(), true),
        ))),
        'api.gg.deals/*' => Http::response(jsonFixture('ggdeals/prices')),
        // Steam appdetails (R25) — zachycené hry podle appids, jinou Steam nezná
        'store.steampowered.com/api/appdetails*' => fn (Request $request): PromiseInterface => Http::response(
            STEAM_DETAILS_FIXTURES[(int) $request['appids']] ?? null
                ? jsonFixture('steam/appdetails-'.STEAM_DETAILS_FIXTURES[(int) $request['appids']])
                : [(string) $request['appids'] => ['success' => false]],
        ),
        'www.cnb.cz/*' => Http::response((string) file_get_contents(fixture('cnb/denni_kurz.txt'))),
        // PlayStation Store — zkrácené skutečné stránky (jen JSON skripty s produkty a cenami)
        'store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT => Http::response(psnFixture('product-control-resonant')),
        'store.playstation.com/cs-cz/concept/'.PSN_CONTROL_RESONANT_CONCEPT => Http::response(psnFixture('concept-control-resonant')),
        'store.playstation.com/cs-cz/product/'.PSN_KINGDOM_COME => Http::response(psnFixture('product-kingdom-come')),
        'store.playstation.com/cs-cz/product/'.PSN_SAROS => Http::response(psnFixture('product-saros')),
        'www.playstation.com/cs-cz/games/saros/' => Http::response(psnFixture('game-page-saros')),
        // Until Dawn — stránka hry bez odkazu do obchodu, jen s konceptem v meta značce (R24)
        'www.playstation.com/cs-cz/games/until-dawn/' => Http::response(psnFixture('game-page-until-dawn')),
        'store.playstation.com/cs-cz/concept/'.PSN_UNTIL_DAWN_CONCEPT => Http::response(psnFixture('concept-until-dawn')),
        'store.playstation.com/cs-cz/product/'.PSN_UNTIL_DAWN => Http::response(psnFixture('product-until-dawn')),
        // It Takes Two — 2 hráči u jedné konzole i online (R25)
        'store.playstation.com/cs-cz/product/'.PSN_IT_TAKES_TWO => Http::response(psnFixture('product-it-takes-two')),
        'store.playstation.com/*' => Http::response('Not Found', 404),
        'www.playstation.com/*' => Http::response('Not Found', 404),
    ];

    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    // Přepsání napřed (vyhrává první shoda); výchozí podvrh se stejným klíčem by
    // v poli přepsal hodnotu přepsání, proto se vynechá
    Http::fake([...$overrides, ...array_diff_key($defaults, $overrides)]);
}

/**
 * Chybová odpověď API ve tvaru ITAD (`status_code`, `reason_phrase`).
 */
function apiError(int $status, string $reason = 'Service Unavailable'): PromiseInterface
{
    return Http::response(['status_code' => $status, 'reason_phrase' => $reason], $status);
}

/**
 * Účet, který sleduje hry z fixtures — převezme je do katalogu i s cenami
 * (přes podvržená API, fakeApis()).
 *
 * @param  list<string>  $itadIds
 * @param  array<string, mixed>  $attributes
 */
function watcherOf(array $itadIds, array $attributes = []): User
{
    $user = user($attributes);
    foreach ($itadIds as $itadId) {
        app(WatchGame::class)->handle($user, $itadId);
    }

    return $user;
}
