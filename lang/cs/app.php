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
        'alerts' => 'upozornění — her: :alerts, e-mailů: :emails, na zařízení: :pushes',
        'alerts_undelivered' => ', nedoručeno: :count (zkusí další běh, podrobnosti v logu)',
        'cache_pruned' => 'cache — smazáno prošlých záznamů: :count',
        'steps' => [
            'exchange_rates' => 'kurzy ČNB',
            'prices' => 'ceny',
            'details' => 'žánry a režimy',
            'alerts' => 'upozornění',
            'cache' => 'cache',
        ],
    ],

    // Upozornění na ceny (etapa 6, R26) — e-mail, telefon a hlášky ze serveru
    'alerts' => [
        'subject_one' => ':title za :price',
        'subject_many' => 'Zlevnila :count sledovaná hra|Zlevnily :count sledované hry|Zlevnilo :count sledovaných her',
        'mail_heading' => 'Hry, které sleduješ, zlevnily',
        'in_shop' => 'v obchodě :shop',
        'in_keyshops' => 'v obchodech s klíči (podle gg.deals)',
        'keyshops' => 'obchody s klíči',
        'reason_target' => 'pod cílovou cenou :target',
        'reason_low' => 'historické minimum',
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

        'nav' => [
            'label' => 'Hlavní navigace',
            'dashboard' => 'Přehled',
            'add_game' => 'Přidat hru',
            'settings' => 'Nastavení',
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
            'plus_catalog' => 'V katalogu PS Plus Extra',
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

        'filters' => [
            'title' => 'Filtry',
            'toggle' => 'Zobrazit filtry',
            'search' => 'Hledat',
            'search_placeholder' => 'Název hry',
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
            'type' => 'Druh',
            'type_game' => 'Hry',
            'type_dlc' => 'DLC',
            'max_price' => 'Cena do',
            'min_cut' => 'Sleva aspoň',
            'deals' => 'Jen ve slevě',
            'plus' => 'Mám PS Plus',
            'plus_hint' => 'U PlayStation Store počítat cenu pro předplatitele',
            'sort' => 'Řadit',
            'sort_added' => 'Naposledy přidané',
            'sort_title' => 'Podle názvu',
            'sort_price' => 'Od nejlevnější',
            'sort_cut' => 'Od největší slevy',
            'sort_release' => 'Od nejnovějšího vydání',
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
            'hint' => 'Katalog je z IsThereAnyDeal. Přesnou hru najdeš podle odkazu na Steam nebo jejího čísla (App ID). Hru na PlayStation najdeš podle odkazu z PlayStation Store nebo playstation.com — produkt se při přidání rovnou připojí.',
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
            'reviews' => 'Hodnocení',
            'reviews_count' => ':count hodnocení',
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

        // Nastavení upozornění (etapa 6, R13, R26)
        'settings' => [
            'title' => 'Nastavení',
            'alerts_title' => 'Upozornění na ceny',
            'alerts_text' => 'Kontrolujeme ceny s každým stažením (každé 4 hodiny). Upozorníme, když hra klesne na cílovou cenu, a u her bez cíle, když je ve slevě na historickém minimu. Stejnou cenu podruhé neposíláme.',
            'email' => 'Posílat e-mailem na :email',
            'ps_plus' => 'Mám PS Plus',
            'ps_plus_hint' => 'Upozornění u PlayStation Store počítají cenu pro předplatitele.',
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
        ],
    ],

];
