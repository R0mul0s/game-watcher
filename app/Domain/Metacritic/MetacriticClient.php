<?php

/**
 * Stahování stránek her z Metacritic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic;

use App\Domain\Metacritic\Exceptions\MetacriticUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stránka hry v anglické verzi s hlavičkami běžného prohlížeče — z hostingu
 * dostupná (docs/research/check-stores.php, R33). Přesměrování se nesleduje:
 * hra pod jiným slugem je pro tuhle adresu „nenalezena“.
 */
final class MetacriticClient
{
    /**
     * Hra podle slugu; null, když ji Metacritic na této adrese nemá.
     *
     * @throws MetacriticUnavailable
     */
    public function game(string $slug): ?MetacriticGame
    {
        $url = MetacriticUrl::game($slug);

        try {
            $response = Http::timeout((int) config('games.sources.metacritic.timeout_seconds'))
                ->withHeaders([
                    'User-Agent' => (string) config('games.sources.metacritic.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning("Metacritic {$url} neodpovídá: ".$e->getMessage());

            throw new MetacriticUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->notFound() || $response->redirect()) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning("Metacritic {$url} vrátil HTTP {$response->status()}.");

            throw new MetacriticUnavailable("HTTP {$response->status()}");
        }

        // Stránka bez dat hry je změna formátu, ne „hra neexistuje“ (R29)
        $game = MetacriticGame::fromHtml($response->body());
        if ($game === null) {
            Log::warning("Metacritic {$url}: stránka nemá strukturovaná data hry — změnil se formát?");

            throw new MetacriticUnavailable((string) __('app.monitoring.metacritic_format', ['slug' => $slug]));
        }

        return $game;
    }
}
