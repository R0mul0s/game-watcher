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
        'www.cnb.cz/*' => Http::response((string) file_get_contents(fixture('cnb/denni_kurz.txt'))),
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
