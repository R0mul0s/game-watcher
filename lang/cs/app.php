<?php

/**
 * Texty aplikace Game Watcher.
 *
 * Skupina `ui` se sdílí do Vue přes Inertia (HandleInertiaRequests) a čte se
 * helperem `t()` z resources/js/lib/i18n.js.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

return [

    'meta' => [
        'description' => 'Soukromé hlídání cen her napříč obchody.',
    ],

    // Přihlášení — hlášky formuláře posílá server, proto mimo skupinu `ui`
    'auth' => [
        'failed' => 'Neplatný e-mail nebo heslo.',
        'throttle' => 'Příliš mnoho pokusů. Zkus to znovu za :seconds s.',
        'email_required' => 'Vyplň e-mail.',
        'email_invalid' => 'Tohle není platný e-mail.',
        'password_required' => 'Vyplň heslo.',
    ],

    // Katalog her z ITAD — hlášky ze serveru (validace, flash), proto mimo skupinu `ui`
    'catalog' => [
        'query_too_long' => 'Hledaný text může mít nejvýš :max znaků.',
        'game_not_found' => 'Tuhle hru se nepodařilo najít v katalogu IsThereAnyDeal.',
        'unavailable' => 'Katalog IsThereAnyDeal teď neodpovídá. Zkus to za chvíli.',
    ],

    // Propojení s PlayStation Store (R20) — hlášky ze serveru
    'listings' => [
        'url_required' => 'Vlož odkaz na hru v PlayStation Store.',
        'not_playstation' => 'Z odkazu nejde najít hru v PlayStation Store. Vlož odkaz na hru v obchodě (store.playstation.com/…/product/… nebo …/concept/…) nebo na stránku hry na playstation.com.',
        'unavailable' => 'PlayStation Store teď neodpovídá. Zkus to za chvíli.',
        'connected' => '„:name“ z PlayStation Store je připojená.',
        'disconnected' => '„:name“ už není připojená.',
    ],

    // Stránka hry na Metacritic připojená odkazem (R33)
    'metacritic' => [
        'url_required' => 'Vlož odkaz na hru na Metacritic.',
        'not_found' => 'Z odkazu nejde najít hru na Metacritic. Vlož odkaz ve tvaru metacritic.com/game/…',
        'unavailable' => 'Metacritic teď neodpovídá. Zkus to za chvíli.',
        'linked' => 'Metacritic je připojený — Metascore :score.',
        'linked_unrated' => 'Metacritic je připojený, „:name“ ale zatím nemá hodnocení kritiků.',
    ],

    // Délka hraní z HowLongToBeat (R44)
    'hltb' => [
        'url_required' => 'Vlož odkaz na hru na HowLongToBeat.',
        'not_found' => 'Z odkazu nejde najít hru na HowLongToBeat. Vlož odkaz ve tvaru howlongtobeat.com/game/…',
        'unavailable' => 'HowLongToBeat teď neodpovídá. Zkus to za chvíli.',
        'linked' => 'HowLongToBeat je připojený — „:name“.',
    ],

    // Nadcházející hry z IGDB (R46)
    'upcoming' => [
        'not_in_catalog' => '„:title“ IsThereAnyDeal zatím nezná — zkus to blíž vydání.',
    ],

    'watchlist' => [
        'added' => '„:title“ je na tvém seznamu.',
        'removed' => '„:title“ už nesleduješ.',
        'added_with_listing' => '„:title“ je na tvém seznamu a „:name“ z PlayStation Store je připojená.',
        'added_connect_playstation' => '„:title“ je na tvém seznamu. Připoj ještě odkaz na PlayStation Store — bez něj hra na PlayStationu nemá cenu.',
        'added_listing_failed' => '„:title“ je na tvém seznamu, ale z odkazu nejde najít hru v PlayStation Store. Připoj ji tady odkazem z obchodu.',
        'added_listing_unavailable' => '„:title“ je na tvém seznamu, ale PlayStation Store teď neodpovídá. Připoj ho tady za chvíli.',
        'platform_changed' => 'Uloženo.',
    ],

    // Odpověď cron URL (prostý text) — čte jen server
    'cron' => [
        'already_running' => 'Předchozí běh ještě neskončil — nic se nestalo.',
        'step_failed' => ':step — chyba, podrobnosti v logu aplikace',
        'source_failed' => 'chyba',
        'exchange_rates_current' => 'kurzy ČNB — aktuální',
        'exchange_rates_imported' => 'kurzy ČNB — uložen lístek z :day',
        'prices' => 'ceny — ITAD her: :itad, gg.deals her: :ggdeals, PlayStation Store produktů: :playstation',
        'details' => 'žánry a režimy ze Steamu — her: :count',
        'details_failed' => 'žánry a režimy ze Steamu — her: :count, Steam neodpovídá (doplní další běh)',
        'info' => 'údaje her z ITAD — obnoveno: :count',
        'info_failed' => 'údaje her z ITAD — obnoveno: :count, ITAD neodpovídá (doplní další běh)',
        'metacritic' => 'Metascore z Metacritic — her: :count',
        'metacritic_failed' => 'Metascore z Metacritic — her: :count, Metacritic neodpovídá (doplní další běh)',
        'hltb' => 'Délka hraní z HowLongToBeat — her: :count',
        'hltb_failed' => 'Délka hraní z HowLongToBeat — her: :count, HowLongToBeat neodpovídá (doplní další běh)',
        'alerts' => 'upozornění — her: :alerts, e-mailů: :emails, na zařízení: :pushes',
        'alerts_undelivered' => ', nedoručeno: :count (zkusí další běh, podrobnosti v logu)',
        'upcoming' => 'nadcházející hry z IGDB — her: :count',
        'upcoming_failed' => 'nadcházející hry z IGDB — IGDB neodpovídá (seznam zůstává z minula)',
        'upcoming_disabled' => 'nadcházející hry — IGDB není nastavené (GAMES_IGDB_CLIENT_ID), přeskočeno',
        'weekly' => 'týdenní souhrn — e-mailů: :count',
        'cache_pruned' => 'cache — smazáno prošlých záznamů: :count',
        'steps' => [
            'exchange_rates' => 'kurzy ČNB',
            'prices' => 'ceny',
            'info' => 'údaje her',
            'details' => 'žánry a režimy',
            'metacritic' => 'Metascore',
            'hltb' => 'délka hraní',
            'upcoming' => 'nadcházející hry',
            'alerts' => 'upozornění',
            'weekly' => 'týdenní souhrn',
            'cache' => 'cache',
        ],
    ],

    // Stav zdrojů (R29) — zprávy chyb ukládané cronem a řádky /health/zdroje
    // (prostý text pro monitoring); názvy zdrojů jsou v ui.sources.names
    'monitoring' => [
        'unexpected_error' => 'Neočekávaná chyba — podrobnosti v logu aplikace',
        'playstation_format' => 'Stránka :path nemá data obchodu ve známém formátu — PS Store nejspíš změnil web',
        'metacritic_format' => 'Stránka hry :slug nemá strukturovaná data hry — Metacritic nejspíš změnil web',
        'hltb_format' => 'HowLongToBeat :path nevrátil data hry — nejspíš změnil web',
        'igdb_token' => 'Twitch odmítl klíč aplikace IGDB (HTTP :status) — zkontroluj GAMES_IGDB_CLIENT_ID a GAMES_IGDB_CLIENT_SECRET',
        'igdb_format' => 'IGDB :path nevrátil očekávaná data — nejspíš změnil API',
        'igdb_empty' => 'IGDB nevrátil žádnou nadcházející hru — seznam zůstává z minula',
        'steam_failed' => 'Steam neodpovídá — údaje doplní další běh',
        'alerts_undelivered' => 'Nedoručená upozornění: :count — podrobnosti v logu aplikace',
        'weekly_undelivered' => 'Nedoručené týdenní souhrny: :count — podrobnosti v logu aplikace',
        'cron_failed' => 'Selhalo: :steps',
        'health' => [
            'ok' => 'OK: poslední úspěch před :minutes min',
            'failing' => 'CHYBA: poslední pokus selhal, úspěch před :minutes min',
            'stale' => 'VÝPADEK: poslední úspěch před :minutes min',
            'cron_ok' => 'OK: poslední běh před :minutes min',
            'cron_failing' => 'CHYBA: poslední běh před :minutes min skončil chybou',
            'cron_stale' => 'VÝPADEK: poslední běh před :minutes min — cron neběží',
            'never' => 'VÝPADEK: zatím neproběhlo',
            'never_succeeded' => 'VÝPADEK: zatím se nepovedlo',
            'optional' => '(nehlídá se) ',
        ],
    ],

    // Upozornění na ceny (etapa 6, R26) — e-mail, telefon a hlášky ze serveru
    'alerts' => [
        'subject_one' => ':title za :price',
        'subject_many' => 'Zlevnila :count sledovaná hra|Zlevnily :count sledované hry|Zlevnilo :count sledovaných her',
        // Upozornění chodí i na vydání a první slevu (R41) — nadpis je obecný
        'mail_heading' => 'Novinky u her, které sleduješ',
        'subject_released' => 'Právě vyšlo: :title za :price',
        'subject_news' => 'Novinky u :count sledované hry|Novinky u :count sledovaných her|Novinky u :count sledovaných her',
        'in_shop' => 'v obchodě :shop',
        'in_keyshops' => 'v obchodech s klíči (podle gg.deals)',
        'keyshops' => 'obchody s klíči',
        'reason_target' => 'pod cílovou cenou :target',
        'reason_low' => 'historické minimum',
        'reason_plus_monthly' => 'tento měsíc zdarma v PS Plus',
        // Hra sledovaná před vydáním (R41)
        'reason_released' => 'právě vyšlo',
        'reason_first_sale' => 'první sleva od vydání',
        // Týdenní souhrn e-mailem (R36)
        'weekly_subject' => 'Za týden zlevnila :count sledovaná hra|Za týden zlevnily :count sledované hry|Za týden zlevnilo :count sledovaných her',
        'weekly_heading' => 'Co za týden zlevnilo',
        'weekly_line' => 'před týdnem :previous · −:drop %',
        'weekly_footer' => 'Souhrn chodí jednou týdně v pondělí, jen když něco zlevnilo. Vypnout ho jde v',
        'open_shop' => 'Do obchodu',
        'open_app' => 'Otevřít Game Watcher',
        'mail_footer' => 'Upozornění chodí na hry z tvého seznamu — cílovou cenu nastavíš na detailu hry, e-mail a telefon v',
        'settings' => 'Nastavení',
        'push_title' => ':title za :price',
        'push_body' => ':reason · :shop',
        'test_title' => 'Game Watcher',
        'test_body' => 'Zkušební upozornění — tohle zařízení je připravené.',
        'device_limit' => 'Na účtu je už moc zařízení. Upozornění na některém vypni a zkus to znovu.',
        'settings_saved' => 'Nastavení je uložené.',
        'target_saved' => 'Cílová cena je uložená.',
        'target_cleared' => 'Cílová cena je zrušená — upozorníme na historické minimum.',
        'target_invalid' => 'Zadej cenu jako číslo, nejvýš se dvěma desetinnými místy.',
        'keys_generated' => 'Klíče VAPID — vlož je do .env (veřejný i soukromý). Po změně klíčů je potřeba upozornění na zařízeních zapnout znovu.',
    ],

    'validation' => [
        'push_endpoint' => 'Adresa odběru musí být HTTPS u známé push služby.',
        'push_key' => 'Klíč odběru nemá správný tvar.',
    ],

    // Zakládání účtů (games:create-user) — jen konzole
    'users' => [
        'password_prompt' => 'Heslo',
        'password_confirm_prompt' => 'Heslo znovu',
        'password_mismatch' => 'Hesla se neshodují.',
        'password_too_short' => 'Heslo musí mít aspoň :min znaků.',
        'email_invalid' => 'Neplatný e-mail.',
        'email_taken' => 'Účet s tímto e-mailem už existuje.',
        'created' => 'Účet :email je založený.',
    ],

    'ui' => [
        'app_name' => 'Game Watcher',
        // Podtitul na přihlašovací stránce
        'tagline' => 'Hlídač cen her',
        'skip_to_content' => 'Přeskočit na obsah',
        // Návrat na začátek stránky (R45)
        'scroll_top' => 'Zpět nahoru',

        // Aplikace na ploše (R37) — stažení dolů a stránka bez připojení
        'pwa' => [
            'refreshing' => 'Načítáme stránku znovu…',
            'offline_title' => 'Jsi offline',
            'offline_text' => 'Game Watcher teď nemá spojení se serverem. Ceny bez připojení neukazujeme — byly by staré. Zkus to znovu, až budeš online.',
            'offline_retry' => 'Zkusit znovu',
        ],

        'nav' => [
            'label' => 'Hlavní navigace',
            'dashboard' => 'Přehled',
            'discover' => 'Objevit',
            'add_game' => 'Přidat hru',
            'settings' => 'Nastavení',
        ],

        // Tlačítko Zpět na detailu hry (R38) — podle stránky hlavní navigace, kam vede
        'back' => [
            'default' => 'Zpět',
            'dashboard' => 'Zpět na přehled',
            'discover' => 'Zpět na objevování',
            'add_game' => 'Zpět na hledání',
            'settings' => 'Zpět do nastavení',
        ],

        'flash' => [
            'dismiss' => 'Zavřít hlášku',
        ],

        'currency' => [
            'label' => 'Měna cen',
        ],

        'prices' => [
            'title' => 'Ceny',
            'best' => 'Nejlevněji',
            'keyshops' => 'Obchody s klíči',
            'keyshops_hint' => 'Nejnižší cena napříč obchody s klíči (G2A, Loaded, Kinguin, Eneba…) podle gg.deals',
            'keyshops_short' => 'klíče od :price',
            'official_short' => 'oficiálně od :price',
            'shop' => 'Obchod',
            'price' => 'Cena',
            'regular' => 'Běžně :price',
            'cut' => '−:cut %',
            'until' => 'do :date',
            'drm' => 'Aktivace: :drm',
            'open_shop' => 'Do obchodu',
            'original' => 'v obchodě :price',
            'lows' => 'Historická minima',
            'low_official' => 'Oficiální obchody',
            'low_keyshops' => 'Obchody s klíči',
            'none' => 'Žádný obchod teď hru neprodává — nebo se ceny ještě nestáhly.',
            'no_price' => 'bez ceny',
            'updated' => 'Aktualizováno :time',
            'sources' => 'Zdroj cen:',
            'plus_price' => 'S PS Plus :price',
            // Úroveň katalogu a měsíční hra PS Plus (R35)
            'plus_catalog_extra' => 'V katalogu PS Plus Extra',
            'plus_catalog_premium' => 'V katalogu PS Plus Premium',
            'plus_catalog_essential' => 'V PS Plus Essential',
            'plus_free' => 'Tento měsíc zdarma v PS Plus',
            'playstation_short' => 'PlayStation od :price',
            'platforms' => [
                'pc' => 'PC',
                'playstation' => 'PlayStation',
            ],
        ],

        'listings' => [
            'title' => 'PlayStation Store',
            'hint' => 'Vlož odkaz na hru v PlayStation Store nebo na její stránku na playstation.com — cena se pak objeví ve výpisu obchodů. Konkrétní edici vybereš odkazem na produkt v obchodě.',
            'label' => 'Odkaz na PlayStation Store',
            'placeholder' => 'https://store.playstation.com/cs-cz/product/…',
            'submit' => 'Připojit',
            'remove' => 'Odpojit',
            'open' => 'Otevřít v obchodě',
        ],

        // Filtry na telefonu v okně (R45)
        'filter_sheet' => [
            'open' => 'Filtry',
            'close' => 'Zavřít filtry',
            'show' => 'Zobrazit :count hru|Zobrazit :count hry|Zobrazit :count her',
            'show_deals' => 'Zobrazit :count slevu|Zobrazit :count slevy|Zobrazit :count slev',
            'show_upcoming' => 'Zobrazit :count hru|Zobrazit :count hry|Zobrazit :count her',
        ],

        'filters' => [
            'title' => 'Filtry',
            'toggle' => 'Zobrazit filtry',
            'search' => 'Hledat',
            'search_placeholder' => 'Název, vývojář, vydavatel, žánr…',
            'platform' => 'Platforma',
            'all' => 'Vše',
            'genres' => 'Žánr',
            'genres_hint' => 'Podle Steamu — stačí jeden z vybraných',
            'players' => 'Hráči',
            'players_hint' => 'Podle Steamu a PlayStation Store',
            'players_options' => [
                'solo' => '1',
                'duo' => '2+',
                'couch' => 'Couch co-op',
            ],
            'players_titles' => [
                'solo' => 'Hra pro jednoho hráče',
                'duo' => 'Pro dva a víc — co-op nebo hra pro víc hráčů, online i u jedné obrazovky',
                'couch' => 'Co-op nebo hra pro víc hráčů u jedné obrazovky (sdílená nebo rozdělená)',
            ],
            // Čeština ve hře (R47) — „titulky“ najdou i hru s dabingem
            'czech' => 'Čeština',
            'czech_hint' => 'Podle Steamu a PlayStation Store — hra bez údaje o jazycích vypadne',
            'czech_options' => [
                'text' => 'Titulky',
                'voice' => 'Dabing',
            ],
            'czech_titles' => [
                'text' => 'Aspoň české titulky nebo rozhraní',
                'voice' => 'Český dabing',
            ],
            'type' => 'Druh',
            'type_game' => 'Hry',
            'type_dlc' => 'DLC',
            // Vydání (R41) — hra bez data vydání se počítá jako nevydaná
            'release' => 'Vydání',
            'release_hint' => 'Hra bez data vydání se počítá jako nevydaná',
            'release_options' => [
                'released' => 'Vydané',
                'upcoming' => 'Nevydané',
            ],
            'max_price' => 'Cena do',
            'min_cut' => 'Sleva aspoň',
            'deals' => 'Jen ve slevě',
            'plus' => 'Mám PS Plus',
            'plus_hint' => 'U PlayStation Store počítat cenu pro předplatitele',
            // Zdroj ceny pro řazení a „cena do“ (R30)
            'price_basis' => 'Cena',
            'price_basis_hint' => 'Podle čeho řadit od nejlevnější a filtrovat „cena do“. Sleva je vždy z oficiálních obchodů — obchody s klíči ji neuvádějí.',
            'price_basis_options' => [
                'official' => 'Oficiální',
                'cheapest' => 'Vše',
                'keyshops' => 'Klíče',
            ],
            'price_basis_titles' => [
                'official' => 'Oficiální obchody a PlayStation Store',
                'cheapest' => 'Nejlevnější nabídka včetně obchodů s klíči',
                'keyshops' => 'Jen obchody s klíči (G2A, Loaded, Kinguin, Eneba… podle gg.deals)',
            ],
            'sort' => 'Řadit',
            'sort_added' => 'Naposledy přidané',
            'sort_title' => 'Podle názvu',
            'sort_price' => 'Od nejlevnější',
            'sort_cut' => 'Od největší slevy',
            'sort_release' => 'Od nejnovějšího vydání',
            'sort_score' => 'Podle Metacritic',
            'sort_per_hour' => 'Od nejlevnější hodiny hraní',
            'reset' => 'Zrušit filtry',
            'shown' => 'Zobrazeno :shown z :total',
            'empty' => 'Filtrům neodpovídá žádná hra.',
        ],

        'auth' => [
            'title' => 'Přihlášení',
            'email' => 'E-mail',
            'password' => 'Heslo',
            'remember' => 'Zůstat přihlášen',
            'submit' => 'Přihlásit',
            'logout' => 'Odhlásit',
            'signed_in_as' => 'Přihlášen jako :name',
        ],

        'dashboard' => [
            'title' => 'Přehled',
            'subtitle' => 'Sleduješ :count hru|Sleduješ :count hry|Sleduješ :count her',
            'add_game' => 'Přidat hru',
            'empty_title' => 'Zatím nic nesleduješ',
            'empty_text' => 'Najdi hru podle názvu nebo odkazu na Steam a přidej si ji na seznam.',
            'connect_playstation' => 'Připoj PlayStation Store',
            'target' => 'cíl :price',
            'target_reached' => 'Pod cílovou cenou',
            // Hodnocení kritiků na kartě (R32)
            'metascore' => 'MC :score',
            'metascore_title' => 'Hodnocení kritiků na Metacritic (Metascore)',
            'metascore_label' => 'Metacritic :score',
            'player_rating' => '★ :rating',
            'player_rating_title' => 'Hodnocení hráčů na PlayStation Store (:count hodnocení)',
            // Délka hlavního příběhu a cena za hodinu (R44)
            'playtime' => ':hours h',
            'playtime_title' => 'Hlavní příběh podle HowLongToBeat',
            'per_hour' => ':price/h',
            'per_hour_title' => 'Cena za hodinu hlavního příběhu',
        ],

        // Stránka Objevit (R43)
        'discover' => [
            'title' => 'Objevit',
            'subtitle' => 'Slevy her, které ještě nesleduješ',
            'tags' => 'Štítky',
            'tags_hint' => 'Ze Steamu — stačí jeden z vybraných, bez výběru všechny hry. Čísla = kolik tvých sledovaných her štítek má.',
            'lows' => 'Jen historická minima',
            'sorts' => [
                'popular' => 'Nejoblíbenější',
                'trending' => 'Teď populární',
                'cut' => 'Od největší slevy',
                'price' => 'Od nejlevnější',
                'newest' => 'Nejnovější slevy',
            ],
            'flags' => [
                'new_low' => 'Nové historické minimum',
                'low' => 'Historické minimum',
                'shop_low' => 'Minimum obchodu',
            ],
            'regular' => 'běžně :price',
            'watch' => 'Sledovat',
            'detail' => 'Detail',
            'empty' => 'Filtrům teď neodpovídá žádná sleva.',
            'unavailable' => 'IsThereAnyDeal teď neodpovídá. Zkus to za chvíli.',
            'source' => 'Slevy v obchodech pro PC podle IsThereAnyDeal — stránka se obnovuje po pár hodinách. PlayStation Store seznam slev nenabízí.',
            // Záložky stránky Objevit (R46)
            'tabs' => [
                'label' => 'Co objevovat',
                'deals' => 'Slevy',
                'upcoming' => 'Nadcházející',
            ],
        ],

        // Záložka Nadcházející na stránce Objevit (R46)
        'upcoming' => [
            'title' => 'Nadcházející hry',
            'subtitle' => 'Co vyjde do roka na PC a PS5 — sleduj hru a dáme vědět, až vyjde a poprvé zlevní',
            'platform' => 'Platforma',
            'platforms' => [
                'all' => 'Všechny',
                'pc' => 'PC',
                'playstation' => 'PS5',
            ],
            'genres' => 'Žánry',
            'genres_hint' => 'Podle IGDB — stačí jeden z vybraných, bez výběru všechny hry.',
            'coop' => 'Jen pro víc hráčů spolu (co-op)',
            'coop_badge' => 'Co-op',
            'sorts' => [
                'date' => 'Od nejbližšího vydání',
                'popular' => 'Nejočekávanější',
            ],
            'early_access' => 'předběžný přístup',
            'quarter' => ':quarter. čtvrtletí :year',
            'watch' => 'Sledovat',
            'detail' => 'Detail',
            'igdb' => 'IGDB',
            'empty' => 'Filtrům teď neodpovídá žádná nadcházející hra.',
            'not_loaded' => 'Seznam nadcházejících her se zatím nestáhl — naplní ho další běh cronu.',
            'source' => 'Data o nadcházejících hrách z IGDB.com — seznam se obnovuje s každým během cronu.',
            'updated' => 'Naposledy :time.',
        ],

        // Slevy dne nad přehledem (R26)
        'deals' => [
            'title' => 'Zlevnilo od včerejška',
            'previous' => 'včera :price',
            'drop' => '−:drop %',
        ],

        'search' => [
            'title' => 'Přidat hru',
            'label' => 'Hra',
            'placeholder' => 'Název, odkaz na Steam nebo na PlayStation Store',
            'hint' => 'Katalog je z IsThereAnyDeal. Přesnou hru najdeš podle odkazu na Steam, jejího čísla (App ID) nebo odkazu na IsThereAnyDeal. Hru na PlayStation najdeš podle odkazu z PlayStation Store nebo playstation.com — produkt se při přidání rovnou připojí.',
            'submit' => 'Hledat',
            'results' => 'Výsledky',
            'no_results' => 'Nic jsme nenašli. Zkus jiný název nebo odkaz na Steam.',
            'unavailable' => 'Katalog IsThereAnyDeal teď neodpovídá. Zkus to za chvíli.',
            'watch' => 'Sledovat',
            'watch_playstation' => 'Jen na PS',
            'watch_playstation_title' => 'Sledovat jen cenu na PlayStationu',
            'watching' => 'Sleduješ',
            'detail' => 'Detail',
            'shops' => 'Obchody',
            'pc' => 'PC',
            'playstation_product' => 'Hry k produktu z PlayStation Store',
            'playstation_connect' => 'Při přidání se rovnou připojí a hra se bude hlídat jen na PlayStationu.',
            'playstation' => 'PlayStation',
            'more_shops' => '+:count',
            'from_price' => 'od :price',
            'free' => 'zdarma',
            'no_pc_shops' => 'V obchodech pro PC se neprodává',
            // Filtry výsledků (R30)
            'filter_type' => 'Druh',
            'types' => [
                'game' => 'Hry',
                'other' => 'DLC a další',
            ],
            'sorts' => [
                'relevance' => 'Podle shody',
                'price' => 'Od nejlevnější',
            ],
            'no_filtered_results' => 'Filtrům neodpovídá žádný výsledek.',
            'show_more' => 'Zobrazit víc výsledků',
        ],

        'game' => [
            // Druhy her z ITAD; neznámý druh se ukáže tak, jak ho ITAD pošle
            'types' => [
                'game' => 'Hra',
                'dlc' => 'DLC',
                'package' => 'Balíček',
            ],
            'mature' => '18+',
            'release_date' => 'Vydání',
            'unreleased' => 'Datum vydání neznámé',
            // Odpočet do vydání (R41)
            'release_tomorrow' => 'vychází zítra',
            'release_in' => 'vychází za :count den|vychází za :count dny|vychází za :count dní',
            'developers' => 'Vývojář',
            'publishers' => 'Vydavatel',
            'tags' => 'Štítky',
            'genres' => 'Žánr',
            'modes' => 'Hráči',
            'mode_names' => [
                'single' => 'Pro jednoho',
                'multi' => 'Pro víc hráčů',
                'coop' => 'Co-op',
                'online_coop' => 'Co-op online',
                'local_coop' => 'Couch co-op',
                'local_multi' => 'Víc hráčů u jedné obrazovky',
            ],
            // Čeština ve hře (R47) — popis na detailu, štítek na kartě
            'czech' => 'Čeština',
            'czech_names' => [
                'none' => 'ne',
                'text' => 'titulky a rozhraní',
                'voice' => 'dabing i titulky',
            ],
            'czech_on_platform' => ':platform — :support',
            'czech_badges' => [
                'text' => 'CZ',
                'voice' => 'CZ dabing',
            ],
            'czech_titles' => [
                'text' => 'České titulky a rozhraní',
                'voice' => 'Český dabing',
            ],
            'reviews' => 'Hodnocení',
            'reviews_count' => ':count hodnocení',
            // Hodnocení hráčů na PS Store a odkaz na Metacritic (R33)
            'player_rating' => 'Hráči na PS Store — :name',
            'player_rating_count' => ':count hodnocení',
            'metacritic_label' => 'Odkaz na Metacritic',
            'metacritic_placeholder' => 'https://www.metacritic.com/game/…',
            'metacritic_submit' => 'Připojit',
            'metacritic_linked' => 'Metascore se stahuje ze stránky',
            'metacritic_not_found' => 'Metacritic jsme podle názvu nenašli — vlož odkaz na stránku hry.',
            'metacritic_pending' => 'Metascore z Metacritic doplní nejbližší stahování cen, nebo vlož odkaz rovnou.',
            'links' => 'Odkazy',
            'steam' => 'Steam',
            'itad' => 'IsThereAnyDeal',
            'watch' => 'Sledovat',
            'unwatch' => 'Přestat sledovat',
            'watching' => 'Sleduješ',
            'watch_platform' => 'Hlídat ceny',
            'target_label' => 'Cílová cena',
            'target_hint' => 'Upozorníme, až nejlevnější nabídka (i s klíči) klesne na cíl nebo pod něj. Bez cíle upozorníme na historické minimum ve slevě.',
            'target_save' => 'Uložit',
            'target_clear' => 'Zrušit cíl',
            'watch_platforms' => [
                'all' => 'Všude',
                'pc' => 'Jen PC',
                'playstation' => 'Jen PlayStation',
            ],
            'source' => 'Údaje o hře: :source',
        ],

        // Graf vývoje ceny na detailu hry (etapa 7, R28)
        'chart' => [
            'title' => 'Vývoj ceny',
            'loading' => 'Načítám historii cen…',
            'empty' => 'Historie cen zatím není.',
            'range' => 'Období',
            'ranges' => [
                'months' => '3 měsíce',
                'year' => 'Rok',
                'all' => 'Vše',
            ],
            'series' => [
                'official' => 'Nejlevněji (PC)',
                'steam' => 'Steam',
                'keyshops' => 'Obchody s klíči',
                'keyshops_low' => 'Minimum klíčů (gg.deals)',
            ],
            'target' => 'cíl :price',
            'note' => 'Nejnižší cena dne v měně účtu, přepočteno dnešním kurzem ČNB. Obchody pro PC podle historie IsThereAnyDeal.',
            'own_since' => 'Obchody s klíči a PlayStation Store sledujeme od :date.',
            'itad_unavailable' => 'Historie z IsThereAnyDeal teď není k dispozici.',
        ],

        // Délka hraní z HowLongToBeat (R44)
        'playtime' => [
            'title' => 'Délka hraní',
            'main' => 'Hlavní příběh',
            'extra' => 'S vedlejšími úkoly',
            'complete' => 'Na 100 %',
            'hours' => ':hours h',
            'per_hour' => 'Cena za hodinu',
            'per_hour_value' => ':price za hodinu příběhu',
            'not_found' => 'HowLongToBeat jsme podle názvu nenašli — vlož odkaz na hru.',
            'pending' => 'Délku hraní doplní nejbližší stahování cen, nebo vlož odkaz rovnou.',
            'link_label' => 'Odkaz na HowLongToBeat',
            'link_placeholder' => 'https://howlongtobeat.com/game/…',
            'link_submit' => 'Připojit',
            'linked' => 'Podle HowLongToBeat:',
            'link_hint' => 'Průměry hráčů z HowLongToBeat.',
        ],

        // „Kup, nebo počkej?“ na detailu hry (R42)
        'outlook' => [
            'title' => 'Kup, nebo počkej?',
            'verdicts' => [
                'buy' => 'Kup teď',
                'good' => 'Dobrá cena',
                'wait' => 'Počkej',
                'neutral' => 'Bez doporučení',
            ],
            'reasons' => [
                'low' => 'Nejnižší cena za poslední rok — levněji to nebývá.',
                'cheap' => 'Levněji byla jen :share % dní za poslední rok.',
                'event' => ':event začíná za :days — loni tam byla za :price.',
                'event_tomorrow' => ':event začíná zítra — loni tam byla za :price.',
                'pattern' => 'Za poslední rok byla levněji :share % dní, u minima naposledy před :ago.',
                'pattern_short' => 'Za poslední rok byla levněji :share % dní.',
                'typical' => 'Běžná cena — za poslední rok byla levněji :share % dní.',
            ],
            'days' => ':count den|:count dny|:count dní',
            'days_ago' => ':count dnem|:count dny|:count dny',
            'cheaper_share' => 'Levněji za rok',
            'cheaper_share_value' => ':share % dní',
            'year_low' => 'Roční minimum',
            'year_low_value' => ':price (−:cut %, :date)',
            'year_low_value_regular' => ':price (:date)',
            'near_low' => 'U minima',
            'near_low_now' => 'teď',
            'near_low_ago' => 'naposledy před :ago',
            'near_low_interval' => 'zhruba každých :days dní',
            'upcoming' => 'Nejbližší výprodej',
            'upcoming_value' => ':event :start – :end',
            'upcoming_last_year' => 'loni :price (−:cut %)',
            'note' => 'Nejnižší cena dne v obchodech pro PC podle IsThereAnyDeal od :since. „U minima“ = nejvýš :percent % nad ročním minimem. Výprodeje podle kalendáře Steamu.',
            'events' => [
                'steam_winter' => 'Zimní výprodej na Steamu',
                'steam_spring' => 'Jarní výprodej na Steamu',
                'steam_summer' => 'Letní výprodej na Steamu',
                'steam_autumn' => 'Podzimní výprodej na Steamu',
            ],
        ],

        // Nastavení upozornění (etapa 6, R13, R26)
        'settings' => [
            'title' => 'Nastavení',
            'alerts_title' => 'Upozornění na ceny',
            'alerts_text' => 'Kontrolujeme ceny s každým stažením (každé 4 hodiny). Upozorníme, když hra klesne na cílovou cenu, a u her bez cíle, když je ve slevě na historickém minimu. Hru sledovanou před vydáním ohlásíme, až vyjde a až bude poprvé ve slevě. Stejnou cenu podruhé neposíláme.',
            'email' => 'Posílat e-mailem na :email',
            'weekly' => 'Týdenní souhrn — v pondělí e-mail s tím, co za týden zlevnilo',
            'ps_plus' => 'Mám PS Plus',
            'ps_plus_hint' => 'Upozornění u PlayStation Store počítají cenu pro předplatitele a dají vědět, když je sledovaná hra měsíční hrou PS Plus.',
            'save' => 'Uložit',
            'push_title' => 'Upozornění na tomto zařízení',
            'push_text' => 'Zpráva přijde hned do telefonu nebo počítače, i když je stránka zavřená.',
            'push_unsupported' => 'Tenhle prohlížeč upozornění neumí.',
            'push_ios' => 'Na iPhonu fungují upozornění jen v aplikaci přidané na plochu: v Safari Sdílet → Přidat na plochu, pak otevřít z plochy (iOS 16.4 a novější).',
            'push_disabled_server' => 'Upozornění na zařízení zatím nejsou na serveru zapnutá.',
            'push_on' => 'Zapnuto na tomto zařízení',
            'push_off' => 'Na tomto zařízení vypnuto',
            'push_enable' => 'Zapnout',
            'push_disable' => 'Vypnout',
            'push_test' => 'Poslat zkušební',
            'push_denied' => 'Prohlížeč upozornění zakázal — povol je v nastavení stránky a zkus to znovu.',
            'push_error' => 'Upozornění se nepodařilo zapnout. Zkus to znovu.',
            'push_test_sent' => 'Zkušební upozornění je odeslané.',
            'push_test_failed' => 'Zkušební upozornění se nepodařilo doručit — zkus upozornění vypnout a zapnout.',
            'devices' => 'Upozornění máš zapnutá na :count zařízení|Upozornění máš zapnutá na :count zařízeních|Upozornění máš zapnutá na :count zařízeních',
            'sources_title' => 'Stav zdrojů',
            'sources_text' => 'Kdy se který zdroj cen naposledy stáhl, kolik toho bylo a poslední chyba.',
            'sources_link' => 'Zobrazit stav zdrojů',
        ],

        // Stav zdrojů cen (R29)
        'sources' => [
            'title' => 'Stav zdrojů',
            'intro' => 'Cron stahuje ceny každé 4 hodiny. Jeden neúspěšný pokus je jen chyba — výpadek je, když se zdroj nepovedl víckrát po sobě. Výpadek hlásí i externí monitoring.',
            'column_source' => 'Zdroj',
            'column_state' => 'Stav',
            'column_last_success' => 'Poslední úspěch',
            'column_last_run' => 'Poslední pokus',
            'column_processed' => 'Zpracováno',
            'column_error' => 'Poslední chyba',
            'processed_of' => ':processed z :expected',
            'failures_in_row' => ':count neúspěšný pokus po sobě|:count neúspěšné pokusy po sobě|:count neúspěšných pokusů po sobě',
            'optional' => 'Doplňkový — výpadek nehlásí monitoring',
            'no_error' => 'Žádná',
            'states' => [
                'ok' => 'V pořádku',
                'failing' => 'Chyba',
                'stale' => 'Výpadek',
                'never' => 'Zatím neběželo',
            ],
            'names' => [
                'cron' => 'Běh cronu',
                'exchange_rates' => 'Kurzy ČNB',
                'itad' => 'IsThereAnyDeal — oficiální obchody',
                'ggdeals' => 'gg.deals — obchody s klíči',
                'playstation' => 'PlayStation Store',
                'game_info' => 'IsThereAnyDeal — obnova údajů her',
                'steam' => 'Steam — žánry a režimy',
                'metacritic' => 'Metacritic — Metascore her mimo ITAD',
                'hltb' => 'HowLongToBeat — délka hraní',
                'igdb' => 'IGDB — nadcházející hry',
                'alerts' => 'Upozornění — e-mail a zařízení',
                'weekly_digest' => 'Týdenní souhrn — e-mail',
            ],
            'dashboard_down' => 'Některé zdroje cen teď nefungují (:sources) — ceny nemusí být aktuální.',
            'dashboard_link' => 'Stav zdrojů',
        ],
    ],

];
