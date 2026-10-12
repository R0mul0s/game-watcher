<?php

/**
 * HTTP klient gg.deals API.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\GgDeals;

use App\Domain\GgDeals\Exceptions\GgDealsUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nejnižší ceny obchodů s klíči podle Steam App ID (R9). API přijímá klíč
 * jen jako parametr `key` v URL (hlavičku nezná — ověřeno 11. 10. 2026),
 * proto se z každé zprávy do logu klíč maže a výjimka nenese původní výjimku.
 */
final class GgDealsClient
{
    private const PRICES_PATH = '/prices/by-steam-app-id/';

    /** Nahrazuje klíč ve zprávách do logu. */
    private const KEY_MASK = '***';

    /**
     * Ceny her podle Steam App ID; hry, které gg.deals nezná, ve výsledku chybí.
     *
     * @param  list<int>  $steamAppIds
     * @return array<int, GgDealsPrices> podle Steam App ID
     *
     * @throws GgDealsUnavailable
     */
    public function prices(array $steamAppIds): array
    {
        try {
            $response = Http::baseUrl((string) config('games.sources.ggdeals.url'))
                ->acceptJson()
                ->timeout((int) config('games.sources.ggdeals.timeout_seconds'))
                ->get(self::PRICES_PATH, [
                    'ids' => implode(',', $steamAppIds),
                    'region' => config('games.sources.ggdeals.region'),
                    'key' => (string) config('games.sources.ggdeals.key'),
                ]);
        } catch (ConnectionException $e) {
            throw $this->fail('neodpovídá: '.$e->getMessage());
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ($data['success'] ?? null) !== true || ! is_array($data['data'] ?? null)) {
            $message = is_array($data) && is_array($data['data'] ?? null) && is_string($data['data']['message'] ?? null)
                ? $data['data']['message']
                : '';

            throw $this->fail("vrátil HTTP {$response->status()} {$message}");
        }

        $prices = [];
        foreach ($data['data'] as $appId => $item) {
            $gamePrices = is_array($item) ? GgDealsPrices::fromApi((int) $appId, $item) : null;
            if ($gamePrices !== null) {
                $prices[$gamePrices->steamAppId] = $gamePrices;
            }
        }

        return $prices;
    }

    /**
     * Zaloguje varování bez klíče a vrátí výjimku.
     */
    private function fail(string $detail): GgDealsUnavailable
    {
        $key = (string) config('games.sources.ggdeals.key');
        $message = 'gg.deals '.($key === '' ? $detail : str_replace($key, self::KEY_MASK, $detail));
        Log::warning($message);

        return new GgDealsUnavailable($message);
    }
}
