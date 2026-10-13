<?php
/**
 * Diagnostika dostupnosti herních obchodů z prostředí sdíleného hostingu.
 * Pro každý obchod zkusí stáhnout cenu testovací hry a vypíše výsledek.
 *
 * Samostatný skript mimo aplikaci (průzkum R7 v docs/PLAN.md, výsledky tamtéž).
 * Použití: nahrát na hosting, otevřít v prohlížeči, po ověření SMAZAT.
 * Lokálně: docker run --rm -v "$PWD/docs/research:/app" -w /app php:8.4-cli php check-stores.php
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */

declare(strict_types=1);

const HTTP_TIMEOUT_SECONDS = 20;
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';
const CENTS_DIVISOR = 100;
const KINGUIN_NO_OFFER = 1000000;

const STEAM_APP_ID = 597820;
const PSN_CONCEPT_URL = 'https://store.playstation.com/cs-cz/concept/229899';
// Stránka hry na playstation.com (R22, R24) — z ní se bere koncept a hledání podle ní pozná hru na PS
const PSN_GAME_PAGE_URL = 'https://www.playstation.com/cs-cz/games/until-dawn/';
const SEARCH_TERM = 'biomutant';
const G2A_PRODUCT_URL = 'https://www.g2a.com/biomutant-steam-key-europe-i10000190196001';
const G2A_ATTEMPTS = 3;
// Metacritic — Metascore exkluzivity PS5, kterou ITAD nemá (R33)
const METACRITIC_GAME_URL = 'https://www.metacritic.com/game/saros/';
// HowLongToBeat — délka hraní (R44); token hledání je vázaný na IP a User-Agent
const HLTB_URL = 'https://howlongtobeat.com';
const SECONDS_PER_HOUR = 3600;
// Nadcházející hry (průzkum 2026-10-13): Steam „Popular Upcoming“, PS Store GraphQL, IGDB
const STEAM_UPCOMING_URL = 'https://store.steampowered.com/search/results/?filter=popularcomingsoon&cc=cz&category1=998&count=10&infinite=1';
const PSN_GRAPHQL_URL = 'https://web.np.playstation.com/api/graphql/v1/op';
// Persisted query webu PS Store pro mřížku kategorie a kategorie „All games“ (při změně webu se mění)
const PSN_CATEGORY_GRID_HASH = '4ce7d410a4db2c8b635a48c1dcec375906ff63b19dadd87e073f8fd0c0481d35';
const PSN_ALL_GAMES_CATEGORY = '28c9c2b2-cecc-415c-9a08-482a605cb104';
const PSN_GRID_SIZE = 10;
const IGDB_GAMES_URL = 'https://api.igdb.com/v4/games';
const TWITCH_TOKEN_URL = 'https://id.twitch.tv/oauth2/token';
const HTTP_UNAUTHORIZED = 401;
const HTTP_BAD_REQUEST = 400;

// Krabicové hry na PS5 z českých e-shopů (průzkum 2026-10-13) — produktové stránky se schema.org
const XZONE_PRODUCT_URL = 'https://www.xzone.cz/ghost-of-yotei-complete-edition-ps5';
const DATART_PRODUCT_URL = 'https://www.datart.cz/hra-sony-playstation-5-astro-bot-ps711000044754.html';
const SMARTY_PRODUCT_URL = 'https://www.smarty.cz/Astro-Bot-PS5--4p146144';
const ALZA_PRODUCT_URL = 'https://www.alza.cz/gaming/astro-bot-ps5-d12388633.htm';
const ZBOZI_PRODUCT_URL = 'https://www.zbozi.cz/vyrobek/astro-bot-ps5/';
const HEUREKA_PRODUCT_URL = 'https://hry-na-ps5.heureka.cz/astro-bot/';

// Veřejné Algolia údaje Ubisoft Store (vložené v HTML jejich stránky, mohou se změnit)
const UBISOFT_ALGOLIA_APP = 'XELY3U4LOD';
const UBISOFT_ALGOLIA_KEY = 'dc39136c7548697cbee2fbc171d04133';
const UBISOFT_ALGOLIA_INDEX = 'production__ie_ubisoft__products__default__best_sellers';
const UBISOFT_SEARCH_TERM = 'assassin';

/**
 * Provede HTTP požadavek s hlavičkami běžného prohlížeče.
 *
 * @param string               $url     Cílová URL
 * @param array<string,string> $headers Dodatečné hlavičky
 * @param string|null          $body    Tělo POST požadavku (null = GET)
 * @param bool                 $ipv4    Jen IPv4 — token HowLongToBeat je vázaný na IP
 * @return array{status:int, body:string, error:string}
 */
function httpRequest(string $url, array $headers = [], ?string $body = null, bool $ipv4 = false): array
{
    $defaultHeaders = [
        'Accept' => 'text/html,application/json,*/*;q=0.8',
        'Accept-Language' => 'cs-CZ,cs;q=0.9,en;q=0.8',
        'Sec-Ch-Ua' => '"Google Chrome";v="141", "Chromium";v="141", "Not?A_Brand";v="24"',
        'Sec-Ch-Ua-Mobile' => '?0',
        'Sec-Ch-Ua-Platform' => '"Windows"',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Upgrade-Insecure-Requests' => '1',
    ];
    $lines = [];
    foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
        $lines[] = $name . ': ' . $value;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => HTTP_TIMEOUT_SECONDS,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_HTTPHEADER => $lines,
        CURLOPT_ENCODING => '',
        // Prázdný cookie jar v paměti – Battle.net bez cookies přesměruje na OAuth (HTTP 401)
        CURLOPT_COOKIEFILE => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($ipv4) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    $response = curl_exec($ch);
    $result = [
        'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'body' => is_string($response) ? $response : '',
        'error' => curl_error($ch),
    ];
    curl_close($ch);

    return $result;
}

/**
 * Pozná stránku s ochranou proti robotům (Cloudflare / Akamai).
 *
 * @param string $body Tělo odpovědi
 * @return bool
 */
function isBlocked(string $body): bool
{
    // Alza má vlastní stránku blokace, Allegro DataDome, Heureka výzvu Cloudflare
    return (bool) preg_match('/Just a moment|cf_challenge|cf_chl|Checking security|Access Denied|errors\.edgesuite|zablokovaly váš přístup|captcha-delivery|datadome/i', $body);
}

/**
 * Steam – veřejné JSON API appdetails.
 *
 * @return string Cena nebo popis chyby
 */
function checkSteam(): string
{
    $r = httpRequest('https://store.steampowered.com/api/appdetails?appids=' . STEAM_APP_ID . '&cc=cz&filters=price_overview');
    $price = json_decode($r['body'], true)[STEAM_APP_ID]['data']['price_overview'] ?? null;

    return $price ? sprintf('%.2f %s', $price['final'] / CENTS_DIVISOR, $price['currency']) : describeFailure($r);
}

/**
 * PlayStation Store – cena z HTML stránky konceptu.
 *
 * @return string Cena nebo popis chyby
 */
function checkPsn(): string
{
    $r = httpRequest(PSN_CONCEPT_URL);
    if (preg_match('/\\\\?"discountedValue\\\\?":(\d+)/', $r['body'], $m)) {
        return sprintf('%.2f CZK', (int) $m[1] / CENTS_DIVISOR);
    }

    return describeFailure($r);
}

/**
 * playstation.com – stránka hry s ID konceptu v meta značce (R22, R24).
 *
 * @return string ID konceptu nebo popis chyby
 */
function checkPsnGamePage(): string
{
    $r = httpRequest(PSN_GAME_PAGE_URL);
    if (preg_match('/<meta\s+name="conceptId"\s+content="(\d+)"/', $r['body'], $m)) {
        return 'koncept ' . $m[1];
    }

    return describeFailure($r);
}

/**
 * Metacritic – Metascore ze strukturovaných dat (schema.org aggregateRating) stránky hry.
 *
 * @return string Metascore s platformou nebo popis chyby
 */
function checkMetacritic(): string
{
    $r = httpRequest(METACRITIC_GAME_URL, ['Accept-Language' => 'en-US,en;q=0.9']);
    if (preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $r['body'], $m)) {
        foreach ($m[1] as $json) {
            $data = json_decode($json, true);
            if (isset($data['aggregateRating']['ratingValue'])) {
                return sprintf('Metascore %d (%s)', $data['aggregateRating']['ratingValue'], implode(', ', (array) ($data['gamePlatform'] ?? [])));
            }
        }
    }

    return describeFailure($r);
}

/**
 * HowLongToBeat – token z /api/search/site/init, pak hledání POST /api/search/site
 * (neveřejné API webu, R44). Délky jsou v sekundách.
 *
 * @return string Délka hlavního příběhu nebo popis chyby
 */
function checkHltb(): string
{
    $referer = ['Referer' => HLTB_URL . '/', 'Accept' => 'application/json'];
    $init = httpRequest(HLTB_URL . '/api/search/site/init?t=' . (int) (microtime(true) * 1000), $referer, null, true);
    $token = json_decode($init['body'], true)['token'] ?? null;
    if (!is_string($token)) {
        return 'token: ' . describeFailure($init);
    }

    $body = json_encode([
        'searchType' => 'games',
        'searchTerms' => [SEARCH_TERM],
        'searchPage' => 1,
        'size' => 20,
        'searchOptions' => ['games' => ['userId' => 0, 'platform' => '', 'sortCategory' => 'popular', 'rangeCategory' => 'main', 'rangeTime' => ['min' => null, 'max' => null], 'gameplay' => ['perspective' => '', 'flow' => '', 'genre' => '', 'difficulty' => ''], 'rangeYear' => ['min' => '', 'max' => ''], 'modifier' => ''], 'users' => ['sortCategory' => 'postcount'], 'lists' => ['sortCategory' => 'follows'], 'filter' => '', 'sort' => 0, 'randomizer' => 0],
        'useCache' => true,
    ]);
    $r = httpRequest(HLTB_URL . '/api/search/site', $referer + ['Content-Type' => 'application/json', 'Origin' => HLTB_URL, 'x-auth-token' => $token], $body, true);
    $game = json_decode($r['body'], true)['data'][0] ?? null;
    if (!is_array($game)) {
        return describeFailure($r);
    }

    return sprintf('%s: hlavní příběh %.1f h', $game['game_name'], $game['comp_main'] / SECONDS_PER_HOUR);
}

/**
 * Steam – „Popular Upcoming“ z vyhledávání obchodu (HTML ve JSON, App ID a datum vydání).
 *
 * @return string Počet her s první hrou nebo popis chyby
 */
function checkSteamUpcoming(): string
{
    $r = httpRequest(STEAM_UPCOMING_URL, ['Accept' => 'application/json']);
    $html = json_decode($r['body'], true)['results_html'] ?? '';
    if (!preg_match_all('/data-ds-appid="(\d+)"/', $html, $ids) || !$ids[1]) {
        return describeFailure($r);
    }
    $date = preg_match('/search_released[^>]*>\s*([^<]+?)\s*</', $html, $m) ? $m[1] : '?';

    return sprintf('%d her, první app %s vychází %s', count($ids[1]), $ids[1][0], $date);
}

/**
 * PlayStation Store – GraphQL mřížka kategorie „All games“ od nejpozdějšího vydání
 * (nahoře jsou nadcházející hry). Locale en-CZ — cs-CZ vrací prázdnou kategorii.
 *
 * @return string Počet konceptů s prvními názvy nebo popis chyby
 */
function checkPsnUpcoming(): string
{
    $variables = [
        'id' => PSN_ALL_GAMES_CATEGORY,
        'pageArgs' => ['size' => PSN_GRID_SIZE, 'offset' => 0],
        'sortBy' => ['name' => 'conceptReleaseDate', 'isAscending' => false],
        'filterBy' => [],
        'facetOptions' => [],
    ];
    $url = PSN_GRAPHQL_URL . '?' . http_build_query([
        'operationName' => 'categoryGridRetrieve',
        'variables' => json_encode($variables),
        'extensions' => json_encode(['persistedQuery' => ['version' => 1, 'sha256Hash' => PSN_CATEGORY_GRID_HASH]]),
    ]);
    $r = httpRequest($url, ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'x-psn-store-locale-override' => 'en-CZ']);
    $concepts = json_decode($r['body'], true)['data']['categoryGridRetrieve']['concepts'] ?? null;
    if (!$concepts) {
        return describeFailure($r);
    }

    return sprintf('%d konceptů: %s', count($concepts), implode(', ', array_slice(array_column($concepts, 'name'), 0, 3)));
}

/**
 * IGDB a Twitch – jen dostupnost: bez klíče IGDB vrací 401 a Twitch 400 (JSON),
 * blokace by vrátila HTML stránku nebo jiný stav.
 *
 * @return string Výsledek obou koncových bodů
 */
function checkIgdb(): string
{
    $igdb = httpRequest(IGDB_GAMES_URL, ['Accept' => 'application/json'], 'fields name; limit 1;');
    $twitch = httpRequest(TWITCH_TOKEN_URL, ['Accept' => 'application/json'], '');
    $igdbOk = $igdb['status'] === HTTP_UNAUTHORIZED && str_contains($igdb['body'], 'Authorization');
    $twitchOk = $twitch['status'] === HTTP_BAD_REQUEST && json_decode($twitch['body'], true) !== null;

    return sprintf(
        'IGDB %s | Twitch %s',
        $igdbOk ? 'dostupné (401 bez klíče)' : describeFailure($igdb),
        $twitchOk ? 'dostupné (400 bez údajů)' : describeFailure($twitch)
    );
}

/**
 * Kinguin – interní JSON API vyhledávání, vrací nejlevnější nabídku.
 *
 * @return string Cena nebo popis chyby
 */
function checkKinguin(): string
{
    $r = httpRequest('https://www.kinguin.net/services/library/api/v1/products/search?phrase=' . SEARCH_TERM . '&size=20&page=0');
    $products = json_decode($r['body'], true)['_embedded']['products'] ?? null;
    if (!$products) {
        return describeFailure($r);
    }
    $prices = array_filter(array_column(array_column($products, 'price'), 'lowestOffer'), fn ($p) => $p < KINGUIN_NO_OFFER);

    return $prices ? sprintf('nejlevnější %.2f EUR (%d produktů)', min($prices) / CENTS_DIVISOR, count($products)) : 'bez nabídek';
}

/**
 * Eneba – data produktů vložená v HTML vyhledávání.
 *
 * @return string Cena nebo popis chyby
 */
function checkEneba(): string
{
    $r = httpRequest('https://www.eneba.com/store/all?text=' . SEARCH_TERM);
    // Sada měn v lowestPrice závisí na zemi IP adresy, proto hledáme EUR kdekoli v objektu
    if (preg_match_all('/"lowestPrice":\{[^}]*"EUR":(\d+)/', $r['body'], $m) && $m[1]) {
        return sprintf('nejlevnější %.2f EUR (%d nabídek)', min(array_map('intval', $m[1])) / CENTS_DIVISOR, count($m[1]));
    }
    if (preg_match('/"lowestPrice":\{[^}]*\}/', $r['body'], $sample)) {
        return 'cena nalezena, ale bez EUR: ' . $sample[0];
    }

    return describeFailure($r);
}

/**
 * Ubisoft Store – veřejný Algolia index.
 *
 * @return string Cena nebo popis chyby
 */
function checkUbisoft(): string
{
    $r = httpRequest(
        'https://' . strtolower(UBISOFT_ALGOLIA_APP) . '-dsn.algolia.net/1/indexes/' . UBISOFT_ALGOLIA_INDEX . '/query',
        ['X-Algolia-Application-Id' => UBISOFT_ALGOLIA_APP, 'X-Algolia-API-Key' => UBISOFT_ALGOLIA_KEY, 'Content-Type' => 'application/json'],
        json_encode(['query' => UBISOFT_SEARCH_TERM, 'hitsPerPage' => 1])
    );
    $hit = json_decode($r['body'], true)['hits'][0] ?? null;

    return $hit ? sprintf('%s: %.2f EUR', $hit['name'], $hit['price']['EUR']) : describeFailure($r);
}

/**
 * G2A – výsledky vyhledávání se načítají až v prohlížeči, cenu proto hledáme
 * ve strukturovaných datech (schema.org) na stránce konkrétního produktu.
 *
 * @return string Cena nebo popis chyby
 */
function checkG2a(): string
{
    $r = httpRequest(G2A_PRODUCT_URL);
    $price = preg_match('/"(?:lowPrice|price)"\s*:\s*"?([\d.]+)"?/', $r['body'], $p) ? $p[1] : null;
    $currency = preg_match('/"priceCurrency"\s*:\s*"(\w+)"/', $r['body'], $c) ? $c[1] : '?';

    return $price !== null ? sprintf('%s %s (schema.org)', $price, $currency) : describeFailure($r);
}

/**
 * Český e-shop – cena produktu ze schema.org: bloky JSON-LD (Product / Offer,
 * u srovnávače AggregateOffer), jinak microdata (Smarty). Nevalidní blok
 * (Datart) se přeskočí.
 *
 * @param string $url URL produktu
 * @return string Cena, dostupnost a EAN, nebo popis chyby
 */
function checkSchemaOffer(string $url): string
{
    $r = httpRequest($url);
    if ($r['status'] !== 200 || isBlocked($r['body'])) {
        return describeFailure($r);
    }

    preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~si', $r['body'], $blocks);
    foreach ($blocks[1] as $json) {
        $data = json_decode(trim($json), true);
        foreach (is_array($data) ? (array_is_list($data) ? $data : [$data]) : [] as $item) {
            if (!is_array($item) || !in_array($item['@type'] ?? null, ['Product', ['Product']], true)) {
                continue;
            }
            $offer = $item['offers'] ?? [];
            $offer = is_array($offer) && array_is_list($offer) ? ($offer[0] ?? []) : $offer;
            $price = $offer['price'] ?? $offer['lowPrice'] ?? $offer['priceSpecification']['price'] ?? null;
            $ean = $item['gtin13'] ?? $item['gtin'] ?? $item['gtin8'] ?? $item['mpn'] ?? '?';

            return sprintf('%s %s, %s, EAN %s (JSON-LD)', $price ?? '?', $offer['priceCurrency'] ?? '?', $offer['availability'] ?? '?', $ean);
        }
    }

    $price = preg_match('/itemprop="price"[^>]*content="([\d.,]+)"/', $r['body'], $p) ? $p[1] : null;
    $availability = preg_match('/itemprop="availability"[^>]*href="([^"]+)"/', $r['body'], $a) ? $a[1] : '?';

    return $price !== null
        ? sprintf('%s, %s (microdata)', $price, $availability)
        : sprintf('stránka dostupná (%d B), cena ve schema.org nenalezena', strlen($r['body']));
}

/**
 * Obchody s ochranou – testuje jen, zda se stránka vůbec načte.
 *
 * @param string $url URL stránky
 * @return string Výsledek
 */
function checkReachable(string $url): string
{
    $r = httpRequest($url);

    return ($r['status'] === 200 && !isBlocked($r['body'])) ? 'stránka dostupná (HTTP 200) – nutno dořešit parsování' : describeFailure($r);
}

/**
 * Sestaví čitelný popis neúspěšné odpovědi.
 *
 * @param array{status:int, body:string, error:string} $r Výsledek httpRequest()
 * @return string
 */
function describeFailure(array $r): string
{
    if ($r['error'] !== '') {
        return 'CHYBA: ' . $r['error'];
    }

    $detail = isBlocked($r['body']) ? ', ochrana proti robotům' : '';
    if ($detail === '' && preg_match('/<title>([^<]*)<\/title>/i', $r['body'], $title)) {
        $detail = sprintf(', %d B, title: "%s"', strlen($r['body']), trim($title[1]));
    }

    return sprintf('NEDOSTUPNÉ (HTTP %d%s)', $r['status'], $detail);
}

$checks = [
    'Steam' => 'checkSteam',
    'PlayStation' => 'checkPsn',
    'PS web' => 'checkPsnGamePage',
    'Metacritic' => 'checkMetacritic',
    'HLTB' => 'checkHltb',
    'Steam brzy' => 'checkSteamUpcoming',
    'PS brzy' => 'checkPsnUpcoming',
    'IGDB' => 'checkIgdb',
    'Kinguin' => 'checkKinguin',
    'Eneba' => 'checkEneba',
    'Ubisoft' => 'checkUbisoft',
    'Epic' => fn () => checkReachable('https://store.epicgames.com/en-US/p/biomutant'),
    // Akamai u G2A propouští nekonzistentně, proto více pokusů
    'G2A' => fn () => implode(' | ', array_map(fn () => checkG2a(), range(1, G2A_ATTEMPTS))),
    'Loaded' => fn () => checkReachable('https://www.loaded.com/catalogsearch/result/?q=' . SEARCH_TERM),
    'Battle.net' => fn () => checkReachable('https://eu.shop.battle.net/en-gb/product/diablo-iv'),
    // Krabicové hry na PS5 (průzkum 2026-10-13) — doma Xzone, Datart a Smarty bez výzvy,
    // Alza jen s plnými hlavičkami prohlížeče, Heureka a Allegro blokují už doma
    'Xzone' => fn () => checkSchemaOffer(XZONE_PRODUCT_URL),
    'Datart' => fn () => checkSchemaOffer(DATART_PRODUCT_URL),
    'Smarty' => fn () => checkSchemaOffer(SMARTY_PRODUCT_URL),
    'Alza' => fn () => checkSchemaOffer(ALZA_PRODUCT_URL),
    'Zbozi.cz' => fn () => checkSchemaOffer(ZBOZI_PRODUCT_URL),
    'Heureka' => fn () => checkReachable(HEUREKA_PRODUCT_URL),
];

header('Content-Type: text/plain; charset=utf-8');
echo 'PHP ' . PHP_VERSION . ' | cURL ' . curl_version()['version'] . ' | SSL ' . curl_version()['ssl_version'] . "\n\n";
foreach ($checks as $store => $check) {
    printf("%-12s %s\n", $store, $check());
}
