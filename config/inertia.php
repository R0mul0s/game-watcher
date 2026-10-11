<?php

/**
 * Konfigurace Inertie — jen to, co se liší od výchozí konfigurace balíčku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

return [

    /*
    | Stránky jsou v resources/js/Pages (velké P), balíček je čeká v js/pages.
    | Lokálně to nevadí — Docker na Windows sdílí složku bez rozlišení velikosti
    | písmen — ale na Linuxu testy stránky nenajdou.
    | Konfigurace balíčku se slučuje jen na nejvyšší úrovni, proto celá sekce.
    */
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => ['vue'],
    ],

];
