{{--
    Kořenová šablona Inertia aplikace.

    @author Roman Hlaváček
    @created 2026-10-11
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover: aplikace z plochy jde až k výřezu displeje, odsazení řeší env(safe-area-inset-*) (R37) --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="{{ __('app.meta.description') }}">
        {{-- Soukromá aplikace — nic z ní nemá být ve vyhledávačích (R3) --}}
        <meta name="robots" content="noindex, nofollow">
        {{-- Název aplikace pro šablonu titulku stránek (resources/js/app.js) --}}
        <meta name="application-name" content="{{ __('app.ui.app_name') }}">
        {{-- Barva lišty prohlížeče na mobilu = pozadí hlavičky (--color-surface v _tokens.scss) --}}
        <meta name="theme-color" content="{{ config('games.theme_color') }}">
        <title inertia>{{ __('app.ui.app_name') }}</title>
        {{-- Ikony generované z resources/images/logo.svg (viz docs/CODING_GUIDELINES.md, sekce 6) --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- Aplikace na ploše — na iPhonu podmínka upozornění na telefon (R27) --}}
        <link rel="manifest" href="/manifest.webmanifest">
        {{-- iPhone (R37): okno bez lišty Safari, název pod ikonou a průhledný stavový řádek
             přes tmavou hlavičku (jen tmavý vzhled, R6) — iOS to z manifestu nebere --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ __('app.ui.app_name') }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
