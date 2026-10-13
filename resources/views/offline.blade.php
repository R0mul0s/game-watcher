{{--
    Stránka bez připojení (R37) — service worker ji ukáže místo chyby prohlížeče,
    když aplikace z plochy nemá síť. Jen styly, žádné skripty ani data účtu:
    ceny bez připojení neukazujeme, byly by staré (R27). Kulisa a logo jsou stejné
    jako na přihlášení (AppBackdrop.vue, BrandLogo.vue).

    @author Roman Hlaváček
    @created 2026-10-13
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="{{ config('games.theme_color') }}">
        <title>{{ __('app.ui.pwa.offline_title') }} · {{ __('app.ui.app_name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/scss/app.scss'])
    </head>
    <body>
        <div class="auth">
            <div class="backdrop backdrop--full" aria-hidden="true">
                <div class="backdrop__horizon"></div>
                <div class="backdrop__floor"></div>
            </div>

            <main class="auth__main">
                <div class="auth__brand">
                    <svg class="brand-logo" viewBox="0 0 48 48" aria-hidden="true">
                        <polygon class="brand-logo__frame" points="24,3 42,13.5 42,34.5 24,45 6,34.5 6,13.5" />
                        <polygon class="brand-logo__play" points="19.5,16 33,24 19.5,32" />
                        <path class="brand-logo__ticks" d="M2 24h5M41 24h5" />
                    </svg>
                    <p class="auth__title">{{ __('app.ui.app_name') }}</p>
                </div>

                <section class="panel auth__panel">
                    <h1 class="panel__title">{{ __('app.ui.pwa.offline_title') }}</h1>
                    <p class="panel__text">{{ __('app.ui.pwa.offline_text') }}</p>
                    <a href="{{ $homeUrl }}" class="button button--primary">{{ __('app.ui.pwa.offline_retry') }}</a>
                </section>
            </main>
        </div>
    </body>
</html>
