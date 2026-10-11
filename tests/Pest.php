<?php

/**
 * Konfigurace Pestu a sdílené pomocné funkce testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
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
 * Podvrhne IsThereAnyDeal API — hledání, lookup a detail her ze zachycených
 * odpovědí. Neznámé ITAD ID vrátí 404 jako skutečné API.
 */
function fakeItad(): void
{
    Http::fake([
        'api.isthereanydeal.com/games/search/v1*' => Http::response(jsonFixture('itad/search-control-resonant')),
        'api.isthereanydeal.com/games/lookup/v1?appid=597820' => Http::response(jsonFixture('itad/lookup-biomutant')),
        'api.isthereanydeal.com/games/lookup/v1*' => Http::response(jsonFixture('itad/lookup-not-found')),
        'api.isthereanydeal.com/games/info/v2?id='.CONTROL_RESONANT_ID => Http::response(jsonFixture('itad/info-control-resonant')),
        'api.isthereanydeal.com/games/info/v2?id='.BIOMUTANT_ID => Http::response(jsonFixture('itad/info-biomutant')),
        'api.isthereanydeal.com/games/info/v2?id='.ALAN_WAKE_2_ID => Http::response(jsonFixture('itad/info-alan-wake-2')),
        'api.isthereanydeal.com/games/info/v2*' => Http::response(['status_code' => 404, 'reason_phrase' => 'Not Found'], 404),
    ]);
}

/**
 * IsThereAnyDeal odpovídá jen chybou (výpadek, neplatný klíč). Nahradí dřívější
 * podvrhy — další Http::fake() by se jen přidal za ně a vyhrála by první shoda.
 */
function fakeItadFailure(int $status, string $reason): void
{
    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    Http::fake(['api.isthereanydeal.com/*' => Http::response(['status_code' => $status, 'reason_phrase' => $reason], $status)]);
}
