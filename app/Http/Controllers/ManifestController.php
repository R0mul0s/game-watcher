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
 * Aplikace na ploše telefonu (R27) — na iPhonu je to podmínka upozornění na
 * telefon (Web Push jen v aplikaci přidané na plochu, iOS 16.4+). Vzor
 * z Počasí; texty z lang, barva z games.theme_color (jen tmavý vzhled, R6).
 */
class ManifestController extends Controller
{
    /** Ikony vygenerované z resources/images/logo.svg: soubor v public/ => rozměr. */
    private const ICONS = [
        '/icon-192.png' => '192x192',
        '/icon-512.png' => '512x512',
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
            'name' => __('app.ui.app_name'),
            'short_name' => __('app.ui.app_name'),
            'id' => $home,
            'description' => __('app.meta.description'),
            'lang' => app()->getLocale(),
            'start_url' => $home,
            'scope' => $home,
            'display' => 'standalone',
            'background_color' => config('games.theme_color'),
            'theme_color' => config('games.theme_color'),
            'icons' => array_map(
                fn (string $src, string $sizes): array => ['src' => $src, 'sizes' => $sizes, 'type' => 'image/png', 'purpose' => 'any'],
                array_keys(self::ICONS),
                self::ICONS,
            ),
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age='.self::MAX_AGE_SECONDS);
    }
}
