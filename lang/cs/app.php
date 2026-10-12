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

    'watchlist' => [
        'added' => '„:title“ je na tvém seznamu.',
        'removed' => '„:title“ už nesleduješ.',
    ],

    // Odpověď cron URL (prostý text) — čte jen server
    'cron' => [
        'already_running' => 'Předchozí běh ještě neskončil — nic se nestalo.',
        'step_failed' => ':step — chyba, podrobnosti v logu aplikace',
        'source_failed' => 'chyba',
        'exchange_rates_current' => 'kurzy ČNB — aktuální',
        'exchange_rates_imported' => 'kurzy ČNB — uložen lístek z :day',
        'prices' => 'ceny — ITAD her: :itad, gg.deals her: :ggdeals',
        'cache_pruned' => 'cache — smazáno prošlých záznamů: :count',
        'steps' => [
            'exchange_rates' => 'kurzy ČNB',
            'prices' => 'ceny',
            'cache' => 'cache',
        ],
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
        ],

        'search' => [
            'title' => 'Přidat hru',
            'label' => 'Hra',
            'placeholder' => 'Název, Steam App ID nebo odkaz na Steam',
            'hint' => 'Katalog je z IsThereAnyDeal. Přesnou hru najdeš podle odkazu na Steam nebo jejího čísla (App ID).',
            'submit' => 'Hledat',
            'results' => 'Výsledky',
            'no_results' => 'Nic jsme nenašli. Zkus jiný název nebo odkaz na Steam.',
            'unavailable' => 'Katalog IsThereAnyDeal teď neodpovídá. Zkus to za chvíli.',
            'watch' => 'Sledovat',
            'watching' => 'Sleduješ',
            'detail' => 'Detail',
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
            'reviews' => 'Hodnocení',
            'reviews_count' => ':count hodnocení',
            'links' => 'Odkazy',
            'steam' => 'Steam',
            'itad' => 'IsThereAnyDeal',
            'watch' => 'Sledovat',
            'unwatch' => 'Přestat sledovat',
            'watching' => 'Sleduješ',
            'source' => 'Údaje o hře: :source',
        ],
    ],

];
