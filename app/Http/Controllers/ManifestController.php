<?php

/**
 * Manifest webové aplikace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Aplikace na ploše telefonu (R27, R37) — na iPhonu je to podmínka upozornění
 * na telefon (Web Push jen v aplikaci přidané na plochu, iOS 16.4+). Texty
 * z lang, barva z games.theme_color (jen tmavý vzhled, R6). Vzor z Počasí
 * a Agregátoru letáků: zkratky po podržení ikony, maskovatelná ikona,
 * `launch_handler` a sdílení odkazu z obchodu do aplikace (Android).
 */
class ManifestController extends Controller
{
    /** Ikony vygenerované z resources/images/logo.svg: soubor v public/ => rozměr. */
    private const ICONS = [
        '/icon-192.png' => '192x192',
        '/icon-512.png' => '512x512',
    ];

    /**
     * Maskovatelná ikona — logo v bezpečné zóně na plném podkladu, systém ji
     * ořízne do kruhu, čtverce nebo kapky (bez ní dá Android logo do bílého kolečka).
     */
    private const MASKABLE_ICON = ['/icon-maskable-512.png', '512x512'];

    /** Zkratky po podržení ikony: název routy => klíč textu v app.ui.nav. */
    private const SHORTCUTS = [
        'dashboard' => 'dashboard',
        'games.search' => 'add_game',
        'settings.show' => 'settings',
    ];

    /** Jak dlouho smí prohlížeč manifest držet v cache [s]. */
    private const MAX_AGE_SECONDS = 86400;

    /**
     * Vrátí manifest jako JSON.
     */
    public function __invoke(): JsonResponse
    {
        $home = route('dashboard', absolute: false);

        return response()->json([
            // Stálá identita aplikace — kdyby se změnila start_url, telefon ji nebude mít dvakrát
            'id' => $home,
            'name' => __('app.ui.app_name'),
            'short_name' => __('app.ui.app_name'),
            'description' => __('app.meta.description'),
            'lang' => app()->getLocale(),
            'dir' => 'ltr',
            'start_url' => $home,
            'scope' => $home,
            'display' => 'standalone',
            'categories' => ['games', 'shopping'],
            // Odkaz z jiné aplikace nebo z upozornění otevře už spuštěnou aplikaci
            // na své adrese — bez toho ji Android jen přenese do popředí (R37)
            'launch_handler' => ['client_mode' => ['navigate-existing', 'auto']],
            'background_color' => config('games.theme_color'),
            'theme_color' => config('games.theme_color'),
            'icons' => [
                ...array_map(
                    fn (string $src, string $sizes): array => ['src' => $src, 'sizes' => $sizes, 'type' => 'image/png', 'purpose' => 'any'],
                    array_keys(self::ICONS),
                    self::ICONS,
                ),
                ['src' => self::MASKABLE_ICON[0], 'sizes' => self::MASKABLE_ICON[1], 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => array_map(fn (string $routeName, string $labelKey): array => [
                'name' => __('app.ui.nav.'.$labelKey),
                'url' => route($routeName, absolute: false),
            ], array_keys(self::SHORTCUTS), self::SHORTCUTS),
            // „Sdílet → Game Watcher“ z obchodu v telefonu otevře Přidat hru s odkazem
            // (jen Android, R37) — GameSearchRequest ho vezme z url nebo text
            'share_target' => [
                'action' => route('games.search', absolute: false),
                'method' => 'GET',
                'params' => ['title' => 'title', 'text' => 'text', 'url' => 'url'],
            ],
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age='.self::MAX_AGE_SECONDS);
    }
}
