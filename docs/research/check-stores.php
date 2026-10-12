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
 * @return array{status:int, body:string, error:string}
 */
function httpRequest(string $url, array $headers = [], ?string $body = null): array
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
    return (bool) preg_match('/Just a moment|cf_challenge|Access Denied|errors\.edgesuite/i', $body);
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
    'Kinguin' => 'checkKinguin',
    'Eneba' => 'checkEneba',
    'Ubisoft' => 'checkUbisoft',
    'Epic' => fn () => checkReachable('https://store.epicgames.com/en-US/p/biomutant'),
    // Akamai u G2A propouští nekonzistentně, proto více pokusů
    'G2A' => fn () => implode(' | ', array_map(fn () => checkG2a(), range(1, G2A_ATTEMPTS))),
    'Loaded' => fn () => checkReachable('https://www.loaded.com/catalogsearch/result/?q=' . SEARCH_TERM),
    'Battle.net' => fn () => checkReachable('https://eu.shop.battle.net/en-gb/product/diablo-iv'),
];

header('Content-Type: text/plain; charset=utf-8');
echo 'PHP ' . PHP_VERSION . ' | cURL ' . curl_version()['version'] . ' | SSL ' . curl_version()['ssl_version'] . "\n\n";
foreach ($checks as $store => $check) {
    printf("%-12s %s\n", $store, $check());
}
