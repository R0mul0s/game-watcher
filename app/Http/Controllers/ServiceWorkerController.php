<?php

/**
 * Service worker pro upozornění na telefon.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Skript musí ležet v kořeni webu (rozsah celý web), proto ho vydává server
 * z resources/service-worker — Vite ho nezpracovává. Prohlížeč se ho ptá
 * na každou změnu, cache ho proto jen ověřuje. Vzor z Počasí (R13).
 */
class ServiceWorkerController extends Controller
{
    /**
     * Vrátí skript service workeru, vždy s ověřením aktuálnosti.
     */
    public function __invoke(): Response
    {
        return response(
            (string) file_get_contents(resource_path('service-worker/service-worker.js')),
            Response::HTTP_OK,
            ['Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'no-cache'],
        );
    }
}
