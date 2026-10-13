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
    | Aplikace na ploše (R37) — po návratu do aplikace z pozadí se data načtou
    | znovu, když jsou starší (ceny se mění s každým během cronu).
    */
    'pwa' => [
        'refresh_after_minutes' => 30,
    ],

    /*
    | Stav zdrojů a monitoring (R29). Cron běží každé 4 hodiny: výpadek cronu
    | je běh starší než 5 h (interval + hodina rezervy), výpadek zdroje úspěch
    | starší než 9 h — jeden neúspěšný běh nevadí, dva po sobě ano.
    | /health/zdroje je veřejná (UptimeRobot), prozrazuje jen stáří.
    */
    'monitoring' => [
        'path' => '/health/zdroje',
        'max_attempts_per_minute' => 30,
        'cron_max_age_minutes' => 300,
        'stale_after_minutes' => 540,
        // Kontrola parserů (R48) jednou denně — první běh cronu po 23 h od minulé;
        // výpadek je úspěch starší než 30 h (den + jeden vynechaný běh cronu a rezerva)
        'canary_interval_minutes' => 1380,
        'canary_max_age_minutes' => 1800,
        // Referenční hry: dlouho v prodeji, s úplnými údaji — když parser u nich
        // údaj nenajde, změnil se formát zdroje (nebo hra zmizela, pak vyměnit)
        'canary' => [
            'playstation_product' => 'EP9000-PPSA15421_00-FULLGAME00000001', // Until Dawn
            'steam_app_id' => 1426210, // It Takes Two
            'hltb_game_id' => 101237, // Alan Wake 2
            'metacritic_slug' => 'alan-wake-ii',
        ],
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
        // „Zobrazit víc“ (R30) — ITAD vrací nejvýš 100, každý výsledek je i dotaz
        // HEAD na playstation.com (v cache týden, R24)
        'search_results_more' => 40,
        // Obnova údajů sledovaných her z ITAD (R34) — snímek starší než tolik dní,
        // nejvýš tolik her za běh cronu (každá je jeden požadavek, limit ITAD R15)
        'info_refresh_days' => 30,
        // Nevydané hry a hry bez data vydání častěji — datum se posouvá a podle
        // něj chodí upozornění „právě vyšlo“ (R41)
        'info_refresh_days_upcoming' => 3,
        'info_refresh_per_run' => 10,
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
        // Týdenní souhrn e-mailem (R36) — den v týdnu (ISO: 1 = pondělí), za kolik
        // dní se pokles počítá a nejvýš kolik her v e-mailu
        'weekly_digest_day' => 1,
        'weekly_digest_days' => 7,
        'weekly_digest_limit' => 15,
        // Hra sledovaná před vydáním (R41): „právě vyšlo“ nejvýš tolik dní po
        // vydání (cron mohl vynechat, datum se mohlo posunout), „první sleva od
        // vydání“ nejvýš tolik dní po vydání
        'release_alert_days' => 14,
        'first_sale_window_days' => 365,
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
    | „Kup, nebo počkej?“ na detailu hry (R42) — ze stejné historie ITAD jako
    | graf, za poslední rok. Roční minimum s tolerancí low_tolerance_percent %
    | (haléřové rozdíly obchodů) = „kup“; „u minima“ je cena do near_low_percent %
    | nad ním. Levněji nejvýš v good_share % dní = dobrá cena, aspoň v wait_share
    | % dní = počkej. Výprodej z kalendáře se ukáže upcoming_days dní předem;
    | historie kratší než min_history_days dní doporučení nedá.
    */
    'outlook' => [
        'low_tolerance_percent' => 2,
        'near_low_percent' => 15,
        'good_share' => 20,
        'wait_share' => 50,
        'upcoming_days' => 60,
        'min_history_days' => 30,
    ],

    /*
    | Stránka Objevit (R43) — slevy z ITAD `deals/v2`. Každá kombinace filtrů je
    | jeden požadavek (limit ITAD, R15), výsledek drží cache cache_seconds.
    | Štítky jsou ze Steamu (anglicky, jak je vrací ITAD): předvolené pro hraní
    | ve dvou a nejčastější štítky sledovaných her.
    */
    'discover' => [
        'limit' => 60,
        'cache_seconds' => 10800,
        'max_tags' => 5,
        'tag_suggestions' => 8,
        'preset_tags' => ['Local Co-Op', 'Co-op', 'Split Screen'],
    ],

    /*
    | Kalendář velkých výprodejů (R42) — data zveřejňuje Valve na Steamworks
    | (partner.steamgames.com/doc/marketing/upcoming_events), PlayStation Store
    | svoje akce předem neohlašuje. Doplňovat ručně, když Valve zveřejní další.
    | key = název v lang (app.ui.outlook.events), shop_id = obchod v ITAD
    | (61 = Steam) — výprodej se ukáže jen u hry, která se v obchodě prodává.
    */
    'sales_calendar' => [
        ['key' => 'steam_winter', 'shop_id' => 61, 'start' => '2026-12-17', 'end' => '2027-01-04'],
        ['key' => 'steam_spring', 'shop_id' => 61, 'start' => '2027-03-18', 'end' => '2027-03-25'],
        ['key' => 'steam_summer', 'shop_id' => 61, 'start' => '2027-06-24', 'end' => '2027-07-08'],
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
            // Předplatné (R50): ITAD vede Game Pass pod jedním ID bez rozlišení
            // PC / Ultimate; jiná předplatná se nehlídají (zvolil uživatel)
            'game_pass_id' => 6,
            // Aktivní bundly (R51): stránka seznamu má nejvýš 50 položek
            // (13. 10. 2026 jich bylo 34), víc stránek jen pojistka
            'bundles_page_size' => 50,
            'bundles_max_pages' => 4,
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
        // Metacritic (R33) — Metascore her, které ITAD nemá (exkluzivity PS5).
        // Hostitel je jediný, který server z vloženého odkazu zavolá (SSRF).
        // Stránky po malých dávkách s pauzou; nenalezená nebo nehodnocená hra
        // se zkusí znovu po refresh_days (hodnocení přichází po vydání).
        'metacritic' => [
            'url' => env('GAMES_METACRITIC_URL', 'https://www.metacritic.com'),
            // Hostitelé odkazu od uživatele — volá se ale vždy jen adresa z `url`
            'hosts' => ['www.metacritic.com', 'metacritic.com'],
            'timeout_seconds' => 15,
            'request_delay_ms' => (int) env('GAMES_METACRITIC_DELAY_MS', 1000),
            'per_run' => 10,
            'refresh_days' => 14,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        ],
        // HowLongToBeat (R44) — délka hraní; neveřejné API webu (hledání s tokenem
        // vázaným na IP a User-Agent, stránka hry s daty Next.js). Hostitelé jsou
        // jediní, které server z vloženého odkazu přijme (SSRF). Po malých dávkách
        // s pauzou, délky se obnovují po refresh_days.
        'hltb' => [
            'url' => env('GAMES_HLTB_URL', 'https://howlongtobeat.com'),
            'hosts' => ['howlongtobeat.com', 'www.howlongtobeat.com'],
            'timeout_seconds' => 15,
            'request_delay_ms' => (int) env('GAMES_HLTB_DELAY_MS', 1000),
            'per_run' => 10,
            'refresh_days' => 30,
            // Token hledání platí omezenou dobu — při odmítnutí se obnoví sám
            'token_cache_seconds' => 600,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        ],
        // IGDB (R46) — nadcházející hry. Oficiální API s klíčem aplikace z Twitche
        // (client credentials): token ~60 dní drží cache, o token_margin_seconds
        // dřív se vyžádá nový. Limit 4 požadavky/s — cron volá jeden dotaz za běh.
        'igdb' => [
            'url' => env('GAMES_IGDB_URL', 'https://api.igdb.com/v4'),
            'token_url' => env('GAMES_IGDB_TOKEN_URL', 'https://id.twitch.tv/oauth2/token'),
            'client_id' => env('GAMES_IGDB_CLIENT_ID'),
            'client_secret' => env('GAMES_IGDB_CLIENT_SECRET'),
            'timeout_seconds' => 15,
            'token_margin_seconds' => 86400,
            // Obrázky — CDN IGDB, velikost podle názvu šablony (t_cover_big 264 × 374,
            // t_screenshot_med 569 × 320 jako banner600 z ITAD)
            'image_url' => 'https://images.igdb.com/igdb/image/upload/t_%s/%s.jpg',
            'cover_size' => 'cover_big',
            'banner_size' => 'screenshot_med',
        ],
    ],

    /*
    | Nadcházející hry (R46, etapa 8) — seznam z IGDB pro záložku Objevit. Cron ho
    | obnoví jedním dotazem za běh: hry vycházející na PC nebo PS5 do window_days
    | dní, s popularitou IGDB (`hypes`) aspoň min_hypes. ID platforem a druhů her
    | jsou číselníky IGDB (ověřené 13. 10. 2026).
    */
    'upcoming' => [
        'window_days' => 365,
        'min_hypes' => 5,
        // Nejvýš tolik her (IGDB vrací nejvýš 500 na dotaz); 13. 10. 2026 jich bylo 253
        'limit' => 500,
        // Platforma IGDB → hlídaná platforma (enum Platform)
        'platforms' => ['pc' => 6, 'playstation' => 167],
        // Druhy her IGDB: 0 hlavní hra, 8 remake, 9 remaster — bez DLC, edic a portů
        'game_types' => [0, 8, 9],
        // Stav hry IGDB, který se vynechá: 6 zrušená, 7 jen fáma
        'skipped_game_statuses' => [6, 7],
        // Stav data vydání IGDB, který vydání neznamená: 1 alfa, 2 beta,
        // 3 předběžný přístup, 4 offline, 5 zrušeno, 34 dřívější přístup pro dražší edici
        // (5 zrušené datum se tak nepočítá ani jako vydání, ani jako nadcházející)
        'prerelease_statuses' => [1, 2, 3, 4, 5, 34],
        // Stav data vydání IGDB „předběžný přístup“ — budoucí start se počítá jako
        // nadcházející vydání se štítkem (na PC se hra tím dnem dá koupit)
        'early_access_status' => 3,
        // Steam v external_games IGDB (external_game_source)
        'steam_source' => 1,
        // Režimy hry IGDB pro filtr co-op: 3 kooperace, 4 rozdělená obrazovka
        'coop_modes' => [3, 4],
        // Nejvýš tolik nejčastějších žánrů ve filtru (IGDB jich má přes 20)
        'genre_suggestions' => 12,
        'site_url' => 'https://www.igdb.com',
    ],

];
