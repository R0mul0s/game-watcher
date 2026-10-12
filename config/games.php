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
        // Obchody ve výsledcích hledání (R23): tyhle se ukazují přednostně
        // a v tomhle pořadí, ostatní jen počtem („+5“)
        'highlight_shops' => ['Steam', 'Epic Game Store', 'GOG', 'Ubisoft Store', 'Blizzard', 'EA Store', 'Microsoft Store'],
        'max_shop_badges' => 4,
    ],

    /*
    | Hlídání cen (etapa 6, R13, R26). Upozornění vyhodnocuje a posílá cron URL
    | po stažení cen — synchronně, bez fronty (R2). Historické minimum PS Store
    | (ITAD ho nezná) se počítá z vlastní historie, až když je dost dlouhá.
    */
    'alerts' => [
        // Nejkratší vlastní historie ceny PS Store pro „historické minimum“ [dny]
        'min_history_days' => 30,
        // Nejvyšší cílová cena (v měně zobrazení) — jen hrubá mez proti překlepům
        'max_target_price' => 100000,
        // Slevy dne na přehledu — nejvýš tolik her a jen pokles aspoň o tolik
        // procent (drobné výkyvy ceny v eurech nejsou sleva)
        'daily_deals_limit' => 8,
        'daily_deals_min_drop' => 5,
        // Víc her najednou přijde na telefon jako jedna souhrnná zpráva
        'max_push_messages' => 3,
    ],

    /*
    | Graf vývoje ceny na detailu hry (etapa 7, R28). Obchody pro PC z historie
    | ITAD (games/history/v2) za history_days dní, obchody s klíči a PS Store
    | z vlastní price_history. Historie ITAD drží cache — detail hry tak ITAD
    | volá nejvýš jednou za cache_seconds.
    */
    'chart' => [
        'history_days' => 730,
        'cache_seconds' => 21600,
        // ID obchodu Steam v ITAD — vlastní čára v grafu
        'steam_shop_id' => 61,
    ],

    /*
    | Upozornění na telefon (Web Push, R13) — převzato z Počasí. Klíče VAPID
    | vygeneruje games:push-keys; bez nich se upozornění na telefon nenabízí.
    */
    'push' => [
        'public_key' => env('GAMES_PUSH_PUBLIC_KEY', ''),
        'private_key' => env('GAMES_PUSH_PRIVATE_KEY', ''),
        // Kontakt pro push službu, když by aplikace posílala nesmysly (mailto: nebo https:)
        'subject' => env('GAMES_PUSH_SUBJECT', ''),
        // Jak dlouho push služba drží zprávu pro vypnutý telefon — sleva vydrží déle než den
        'ttl_seconds' => 86400,
        'timeout_seconds' => 10,
        // Odběr jen u známých push služeb — jinak by server posílal požadavky na
        // libovolnou podstrčenou adresu (SSRF). Vzory pro Str::is (Chrome a Android,
        // Firefox, Safari, Edge na Windows)
        'allowed_hosts' => [
            'fcm.googleapis.com',
            '*.push.services.mozilla.com',
            'web.push.apple.com',
            '*.notify.windows.com',
        ],
        // Nejvýš tolik zařízení na účet
        'max_subscriptions_per_user' => 10,
        // Požadavků na odběr a zkušebních upozornění za minutu
        'max_attempts_per_minute' => 10,
    ],

    /*
    | Propojení her s produkty obchodů odkazem (R20) — každé propojení stahuje
    | stránku obchodu, proto omezený počet za minutu.
    */
    'listings' => [
        'max_attempts_per_minute' => 10,
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
        // Steam — žánry a režimy hry (R25) z veřejného appdetails bez klíče.
        // Limit Steamu je zhruba 200 požadavků za 5 minut — cron doplní nejvýš
        // details_per_run her s pauzou a údaje obnovuje po details_refresh_days
        'steam' => [
            'url' => env('GAMES_STEAM_URL', 'https://store.steampowered.com/api'),
            'country' => 'cz',
            'language' => 'czech',
            'timeout_seconds' => 10,
            'request_delay_ms' => (int) env('GAMES_STEAM_DELAY_MS', 500),
            'details_per_run' => 20,
            'details_refresh_days' => 30,
        ],
        // PlayStation Store (R20) — stránky produktů v české verzi, ceny v CZK.
        // Hostitel je zároveň jediný, který server z vloženého odkazu zavolá (SSRF).
        'playstation' => [
            'url' => env('GAMES_PLAYSTATION_URL', 'https://store.playstation.com'),
            'host' => 'store.playstation.com',
            // Prezentační web se stránkami her — z nich se vezme odkaz do obchodu (R22)
            'site_url' => env('GAMES_PLAYSTATION_SITE_URL', 'https://www.playstation.com'),
            'site_host' => 'www.playstation.com',
            'locale' => 'cs-cz',
            'timeout_seconds' => 15,
            // Šířka obrázků z obrázkového API PlayStationu (originály až 3840 px) —
            // obal na výšku jako obal z ITAD (300 × 450), banner jako banner600 z ITAD
            'cover_width' => 400,
            'banner_width' => 600,
            // Pauza mezi stránkami — šetrnost k obchodu (CODING_GUIDELINES, sekce 3)
            'request_delay_ms' => (int) env('GAMES_PLAYSTATION_DELAY_MS', 500),
            // Je hra z hledání na PlayStationu? Ověřuje se existence stránky hry
            // na www.playstation.com dotazem HEAD (R24) — nejvýš tolik souběžně;
            // výsledek drží cache týden (stránky her se nemění)
            'game_page_concurrency' => 4,
            'game_page_cache_seconds' => 604800,
            // Akamai obchodu odmítá klienty bez hlaviček běžného prohlížeče (R7)
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        ],
    ],

];
