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
use Minishlink\WebPush\VAPID;
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
/** Nadcházející hry ze zachyceného seznamu IGDB (tests/Fixtures/igdb/, R46). */
const FABLE_ID = '018d937f-4b7e-7321-bea0-0a3d69263d30';
const FABLE_STEAM_ID = 2769570;
const GTA_VI_ID = '019e3a88-379d-701c-9a03-5054b5450244';

/** Produkty PlayStation Store ze zachycených stránek (tests/Fixtures/playstation/). */
const PSN_CONTROL_RESONANT = 'EP5291-PPSA34547_00-0210080241603648';
const PSN_CONTROL_RESONANT_CONCEPT = '10017682';
const PSN_KINGDOM_COME = 'EP4062-PPSA28731_00-0405812491993057';
const PSN_KCD2_EXPANSION_US = 'UP2047-PPSA21705_00-KINGDOM2EXPASIEA';
const PSN_SAROS = 'EP9000-PPSA07631_00-SAROS00000000000';
const PSN_UNTIL_DAWN = 'EP9000-PPSA15421_00-FULLGAME00000001';
const PSN_UNTIL_DAWN_CONCEPT = '10007897';
const PSN_IT_TAKES_TWO = 'EP0006-PPSA02343_00-ITTAKESTWORETAIL';
/** Měsíční hra PS Plus Essential (tlačítko UPSELL_PS_PLUS_FREE, R35). */
const PSN_F1_25 = 'EP4291-PPSA16357_00-0286411811680806';
/** Odchází z katalogu PS Plus Extra 20. 10. 2026 (R49). */
const PSN_SILENT_HILL_2 = 'EP0101-PPSA08709_00-MAINGAME00000000';

// Steam App ID se zachycenými údaji appdetails (tests/Fixtures/steam)
const IT_TAKES_TWO_STEAM_ID = 1426210;
/** České titulky bez dabingu (R47). */
const CYBERPUNK_STEAM_ID = 1091500;
/** Český dabing (R47). */
const KINGDOM_COME_2_STEAM_ID = 1771300;
const STEAM_DETAILS_FIXTURES = [
    597820 => 'biomutant',
    3669870 => 'control-resonant',
    IT_TAKES_TWO_STEAM_ID => 'it-takes-two',
    CYBERPUNK_STEAM_ID => 'cyberpunk-2077',
    KINGDOM_COME_2_STEAM_ID => 'kingdom-come-2',
];

/**
 * Zkrácená stránka PlayStation Store zachycená 12. 10. 2026.
 */
function psnFixture(string $name): string
{
    return (string) file_get_contents(fixture("playstation/{$name}.html"));
}

/** Hledání na HowLongToBeat zachycená 13. 10. 2026 — slova názvu => tests/Fixtures/hltb/search-<soubor>.json. */
const HLTB_SEARCH_FIXTURES = [
    'biomutant' => 'biomutant',
    'control resonant' => 'control-resonant',
    'saros' => 'saros',
    'alan wake 2' => 'alan-wake-2',
];

/**
 * Zkrácená stránka hry na HowLongToBeat zachycená 13. 10. 2026 (jen <title> a data hry z __NEXT_DATA__).
 */
function hltbFixture(int $id): string
{
    return (string) file_get_contents(fixture("hltb/game-{$id}.html"));
}

/**
 * Zkrácená stránka hry na Metacritic zachycená 12. 10. 2026 (jen <title> a JSON-LD).
 */
function metacriticFixture(string $slug): string
{
    return (string) file_get_contents(fixture("metacritic/game-{$slug}.html"));
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
        // Nadcházející hry (R46) — Fable podle Steam App ID, GTA VI (jen PS5) podle názvu
        'api.isthereanydeal.com/games/lookup/v1?appid='.FABLE_STEAM_ID => Http::response(jsonFixture('itad/lookup-fable')),
        'api.isthereanydeal.com/games/lookup/v1?title=Grand*' => Http::response(jsonFixture('itad/lookup-gta-vi')),
        'api.isthereanydeal.com/games/info/v2?id='.FABLE_ID => Http::response(jsonFixture('itad/info-fable')),
        'api.isthereanydeal.com/games/info/v2?id='.GTA_VI_ID => Http::response(jsonFixture('itad/info-gta-vi')),
        'api.isthereanydeal.com/games/lookup/v1*' => Http::response(jsonFixture('itad/lookup-not-found')),
        'api.isthereanydeal.com/games/info/v2?id='.CONTROL_RESONANT_ID => Http::response(jsonFixture('itad/info-control-resonant')),
        'api.isthereanydeal.com/games/info/v2?id='.BIOMUTANT_ID => Http::response(jsonFixture('itad/info-biomutant')),
        'api.isthereanydeal.com/games/info/v2?id='.ALAN_WAKE_2_ID => Http::response(jsonFixture('itad/info-alan-wake-2')),
        'api.isthereanydeal.com/games/info/v2*' => apiError(404, 'Not Found'),
        // Zachycené ceny tří her — vrátí jen hry, na které se požadavek ptá, jako skutečné API
        // Historie cen (R28) — zachycená za rok jen u BIOMUTANT, ostatní hry bez historie
        'api.isthereanydeal.com/games/history/v2?id='.BIOMUTANT_ID.'*' => Http::response(jsonFixture('itad/history-biomutant')),
        'api.isthereanydeal.com/games/history/v2*' => Http::response([]),
        // Seznam slev (R43) — zachycený 13. 10. 2026 (hry, štítky Local Co-Op a RPG, historická minima)
        'api.isthereanydeal.com/deals/v2*' => Http::response(jsonFixture('itad/deals')),
        'api.isthereanydeal.com/games/prices/v3*' => fn (Request $request): PromiseInterface => Http::response(array_values(array_filter(
            jsonFixture('itad/prices'),
            fn (array $game): bool => in_array($game['id'], $request->data(), true),
        ))),
        // Předplatná (R50) — zachycená 13. 10. 2026; Game Pass má z fixtur jen Fable,
        // hry bez předplatného v odpovědi chybí jako ve skutečném API
        'api.isthereanydeal.com/games/subs/v1*' => fn (Request $request): PromiseInterface => Http::response(array_values(array_filter(
            jsonFixture('itad/subs'),
            fn (array $game): bool => in_array($game['id'], $request->data(), true),
        ))),
        // Aktivní bundly (R51) — 4 z 34 zachycených 13. 10. 2026, bez obrázků
        'api.isthereanydeal.com/bundles/v1*' => Http::response(jsonFixture('itad/bundles')),
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
        // F1 25 — měsíční hra PS Plus Essential (R35)
        'store.playstation.com/cs-cz/product/'.PSN_F1_25 => Http::response(psnFixture('product-f1-25')),
        // Silent Hill 2 — odchází z katalogu PS Plus Extra (R49)
        'store.playstation.com/cs-cz/product/'.PSN_SILENT_HILL_2 => Http::response(psnFixture('product-silent-hill-2')),
        'store.playstation.com/*' => Http::response('Not Found', 404),
        'www.playstation.com/*' => Http::response('Not Found', 404),
        // Metacritic (R33) — zkrácené skutečné stránky (jen JSON-LD), jiná hra 404
        'www.metacritic.com/game/saros/' => Http::response(metacriticFixture('saros')),
        'www.metacritic.com/game/alan-wake-ii/' => Http::response(metacriticFixture('alan-wake-ii')),
        'www.metacritic.com/game/intergalactic-the-heretic-prophet/' => Http::response(metacriticFixture('intergalactic-the-heretic-prophet')),
        'www.metacritic.com/*' => Http::response('Not Found', 404),
        // HowLongToBeat (R44) — token (vymyšlený, skutečný obsahuje IP), zachycené
        // hledání podle slov názvu (jiná hra nic nenajde) a zkrácené stránky her
        'howlongtobeat.com/api/search/site/init*' => Http::response(jsonFixture('hltb/init')),
        'howlongtobeat.com/api/search/site' => fn (Request $request): PromiseInterface => Http::response(
            ($search = HLTB_SEARCH_FIXTURES[mb_strtolower(implode(' ', (array) $request['searchTerms']))] ?? null) === null
                ? ['count' => 0, 'data' => []]
                : jsonFixture('hltb/search-'.$search),
        ),
        'howlongtobeat.com/game/48568' => Http::response(hltbFixture(48568)),
        'howlongtobeat.com/game/101237' => Http::response(hltbFixture(101237)),
        'howlongtobeat.com/*' => Http::response('Not Found', 404),
        // IGDB (R46) — token aplikace (vymyšlený) a zachycený seznam nadcházejících her
        'id.twitch.tv/oauth2/token' => Http::response(jsonFixture('igdb/token')),
        'api.igdb.com/v4/games' => Http::response(jsonFixture('igdb/games-upcoming')),
    ];

    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    // Přepsání napřed (vyhrává první shoda); výchozí podvrh se stejným klíčem by
    // v poli přepsal hodnotu přepsání, proto se vynechá
    Http::fake([...$overrides, ...array_diff_key($defaults, $overrides)]);
}

/**
 * Text s nezlomitelnými mezerami (Intl je dává mezi částku a měnu) jako s obyčejnými.
 */
function plainSpaces(string $text): string
{
    return str_replace(["\u{a0}", "\u{202f}"], ' ', $text);
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

/**
 * Zapne upozornění na telefon — vygeneruje klíče VAPID (v phpunit.xml jsou
 * prázdné). Převzato z Počasí.
 */
function enablePush(): void
{
    $keys = VAPID::createVapidKeys();

    config([
        'games.push.public_key' => $keys['publicKey'],
        'games.push.private_key' => $keys['privateKey'],
        'games.push.subject' => 'mailto:test@example.com',
    ]);
}

/**
 * Odběr Web Push, jak ho pošle prohlížeč (PushSubscription.toJSON() + kódování),
 * se skutečnými klíči P-256 — jinak by zprávu nešlo zašifrovat. Z Počasí.
 *
 * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, content_encoding: string}
 */
function browserSubscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/test-device'): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($key)['ec'];
    $base64Url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => $base64Url("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'auth' => $base64Url(random_bytes(16)),
        ],
        'content_encoding' => 'aes128gcm',
    ];
}
