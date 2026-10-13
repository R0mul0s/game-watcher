<?php

/**
 * Klient veřejného API obchodu Steam.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Steam;

use App\Domain\Steam\Exceptions\SteamUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint appdetails bez klíče (R25) — jedna hra na požadavek, limit Steamu
 * je zhruba 200 požadavků za 5 minut, proto se stahuje po malých dávkách
 * s pauzou (games.sources.steam). Z hostingu dostupný (průzkum R7).
 */
final class SteamClient
{
    /** Části odpovědi appdetails — basic kvůli seznamu jazyků (R47), samostatně ho Steam nevrátí. */
    private const FILTERS = 'basic,categories,genres';

    /**
     * Žánry, režimy a čeština hry; null, když Steam hru nezná nebo ji nenabízí
     * (stažená, jen v jiné zemi).
     *
     * @throws SteamUnavailable
     */
    public function appDetails(int $appId): ?SteamAppDetails
    {
        $url = rtrim((string) config('games.sources.steam.url'), '/').'/appdetails';

        try {
            $response = Http::timeout((int) config('games.sources.steam.timeout_seconds'))
                ->acceptJson()
                ->get($url, [
                    'appids' => $appId,
                    'cc' => config('games.sources.steam.country'),
                    'l' => config('games.sources.steam.language'),
                    'filters' => self::FILTERS,
                ]);
        } catch (ConnectionException $e) {
            Log::warning("Steam appdetails {$appId} neodpovídá: ".$e->getMessage());

            throw new SteamUnavailable($e->getMessage(), previous: $e);
        }

        $app = $response->successful() ? $response->json((string) $appId) : null;
        if (! is_array($app)) {
            Log::warning("Steam appdetails {$appId} vrátil HTTP {$response->status()} bez údajů o hře.");

            throw new SteamUnavailable("HTTP {$response->status()}");
        }

        return ($app['success'] ?? false) === true && is_array($app['data'] ?? null)
            ? SteamAppDetails::fromApi($app['data'])
            : null;
    }
}
