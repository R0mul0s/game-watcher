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
            'empty_title' => 'Zatím nic nesleduješ',
            'empty_text' => 'Přidávání her a hlídání cen se připravuje.',
        ],
    ],

];
