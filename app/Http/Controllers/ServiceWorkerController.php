<?php

/**
 * Service worker aplikace a stránka bez připojení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Skript musí ležet v kořeni webu (rozsah celý web), proto ho vydává server
 * z resources/service-worker — Vite ho nezpracovává. Před skript doplní
 * `self.SW_CONFIG`: verzi (nový build nebo skript = nová verze, prohlížeč
 * service worker vymění) a soubory stránky bez připojení (R37). Prohlížeč se ho
 * ptá na každou změnu, cache ho proto jen ověřuje. Vzor z Počasí (R13)
 * a Agregátoru letáků.
 */
class ServiceWorkerController extends Controller
{
    /** Zdroj skriptu service workeru. */
    private const SCRIPT_PATH = 'service-worker/service-worker.js';

    /** Manifest buildu Vite — z něj se berou styly stránky bez připojení. */
    private const BUILD_MANIFEST = 'build/manifest.json';

    private const BUILD_DIRECTORY = '/build/';

    /** Vstup Vite se styly aplikace (stránka bez připojení nemá skripty). */
    private const STYLES_ENTRY = 'resources/scss/app.scss';

    /** Délka verze service workeru (začátek SHA-256). */
    private const VERSION_LENGTH = 16;

    /** Ikona upozornění — ikona aplikace. */
    private const NOTIFICATION_ICON = '/icon-192.png';

    /**
     * Vrátí skript service workeru s nastavením, vždy s ověřením aktuálnosti.
     */
    public function __invoke(): Response
    {
        $source = (string) file_get_contents(resource_path(self::SCRIPT_PATH));
        $manifestPath = public_path(self::BUILD_MANIFEST);
        $manifest = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : '';
        $offlineUrl = route('offline', absolute: false);

        $config = [
            'version' => substr(hash('sha256', $manifest.$source), 0, self::VERSION_LENGTH),
            'offlineUrl' => $offlineUrl,
            'precache' => [$offlineUrl, ...$this->styles($manifest), self::NOTIFICATION_ICON],
            'notificationIcon' => self::NOTIFICATION_ICON,
        ];

        return response(
            'self.SW_CONFIG = '.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).";\n\n".$source,
            Response::HTTP_OK,
            ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'no-cache'],
        );
    }

    /**
     * Stránka bez připojení — service worker ji ukáže místo chyby prohlížeče (R37).
     * Bez cen a dat účtu: staré ceny by klamaly (R27).
     */
    public function offline(): View
    {
        return view('offline', ['homeUrl' => route('dashboard', absolute: false)]);
    }

    /**
     * Styly aplikace z buildu jako adresy od kořene webu; bez buildu žádné.
     *
     * @return list<string>
     */
    private function styles(string $manifest): array
    {
        $entries = json_decode($manifest, true);
        $entry = is_array($entries) ? ($entries[self::STYLES_ENTRY] ?? null) : null;
        $file = is_array($entry) && is_string($entry['file'] ?? null) ? $entry['file'] : null;

        return $file === null ? [] : [self::BUILD_DIRECTORY.$file];
    }
}
