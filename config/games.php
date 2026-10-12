<?php

/**
 * Konfigurace aplikace Game Watcher — konstanty projektu a zdroje cen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

return [

    /*
    | Barva lišty prohlížeče na mobilu (meta theme-color) — musí odpovídat
    | --color-surface v resources/scss/base/_tokens.scss, tedy pozadí hlavičky:
    | iOS jinak dělá mezi stavovým řádkem a hlavičkou viditelný přechod
    | (zkušenost z projektu Počasí). Vzhled je jen tmavý (R6).
    */
    'theme_color' => '#070b16',

    /*
    | Časová zóna pro zobrazení a místní den (historie cen, platnost kurzu).
    | Aplikace i databáze pracují v UTC.
    */
    'display_timezone' => env('GAMES_DISPLAY_TIMEZONE', 'Europe/Prague'),

    /*
    | Cron hostingu volá URL s tokenem (R2) — stahování kurzů a cen. Prázdný
    | token URL vypíná (404). Zámek brání souběhu, když předchozí běh trvá déle.
    */
    'cron' => [
        'token' => env('GAMES_CRON_TOKEN'),
        'lock_seconds' => 600,
        'max_attempts_per_minute' => 10,
    ],

    /*
    | Ceny (etapa 3, R9, R14). Dávky odpovídají limitům API: ITAD bere až 200
    | her v jednom požadavku; gg.deals limit nezveřejňuje — 100 je opatrný odhad.
    | Zobrazovací měny přepíná uživatel v hlavičce (users.display_currency).
    */
    'prices' => [
        'itad_batch_size' => 200,
        'ggdeals_batch_size' => 100,
        'display_currencies' => ['CZK', 'EUR'],
        'default_currency' => 'CZK',
    ],

    /*
    | Denní kurzy ČNB (R14) — textový lístek bez klíče. Stahuje se, jen když
    | uložený kurz není dnešní, nejvýš jednou za retry_minutes (ČNB vyhlašuje
    | v pracovní dny odpoledne).
    */
    'exchange_rates' => [
        'url' => env('GAMES_CNB_URL', 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt'),
        'timeout_seconds' => 10,
        'retry_minutes' => 60,
    ],

    /*
    | Přihlášení (R4). Pokusy o přihlášení se počítají na kombinaci e-mailu a IP;
    | po vyčerpání je přihlášení zablokované na lockout_seconds.
    */
    'auth' => [
        'max_attempts' => 5,
        'lockout_seconds' => 60,
        // Nejkratší heslo při zakládání účtu (games:create-user)
        'min_password_length' => 12,
    ],

    /*
    | Katalog her z IsThereAnyDeal (etapa 2, R10). ITAD povoluje 100 požadavků
    | za 5 minut na klíč — hledá se jen po odeslání formuláře, výsledek se drží
    | v cache a počet hledání na účet je omezený.
    */
    'catalog' => [
        'search_results' => 12,
        'search_cache_seconds' => 3600,
        'max_searches_per_minute' => 20,
        // Nejdelší hledaný text — název hry nebo odkaz na Steam
        'max_query_length' => 200,
        'steam_store_url' => 'https://store.steampowered.com/app/%d/',
    ],

    /*
    | Zdroje cen (R9). Klíče jsou tajemství a patří do .env; adresy se mění jen
    | výjimečně. Používat je začne etapa 2 a 3 (docs/PLAN.md).
    |
    | IsThereAnyDeal — katalog her (ITAD ID, název, obrázky) a ceny oficiálních
    | obchodů včetně Steamu, Epicu, Ubisoftu a Battle.netu pro zemi `country`.
    | gg.deals — nejnižší cena napříč obchody s klíči podle Steam App ID pro
    | region `region` (CZ API nezná, eu = ceny v EUR).
    */
    'sources' => [
        'itad' => [
            'url' => env('GAMES_ITAD_URL', 'https://api.isthereanydeal.com'),
            // Posílá se v hlavičce ITAD-API-Key, ne v URL — adresa požadavku
            // se objevuje v chybách a logu
            'key' => env('GAMES_ITAD_API_KEY'),
            'country' => 'CZ',
            'timeout_seconds' => 10,
        ],
        'ggdeals' => [
            'url' => env('GAMES_GGDEALS_URL', 'https://api.gg.deals/v1'),
            // Jen v URL (hlavičku API nezná) — GgDealsClient ho z logu maže
            'key' => env('GAMES_GGDEALS_API_KEY'),
            'region' => 'eu',
            'timeout_seconds' => 10,
            // Stránka gg.deals — povinný odkaz u zobrazených dat
            'site_url' => 'https://gg.deals',
        ],
    ],

];
