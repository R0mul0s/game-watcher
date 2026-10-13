<?php

/**
 * HTTP klient obchodů s krabicovými hrami.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail;

use App\Domain\Retail\Exceptions\RetailFormatChanged;
use App\Domain\Retail\Exceptions\RetailUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stáhne stránku produktu Xzone nebo Heureky (R52) — jen adresu z RetailUrl
 * (SSRF). Hlavičky běžného prohlížeče: Heureka bez nich ukáže výzvu Cloudflare.
 * Stránka neexistuje = null; výzva, chyba nebo stránka bez produktu = výpadek
 * (RetailUnavailable), nikdy důvod mazat cenu (R29).
 */
final class RetailClient
{
    /** Stránka výzvy Cloudflare místo obsahu. */
    private const CHALLENGE_PATTERN = '~Just a moment|cf_chl|Attention Required!|Checking security~i';

    /** Produkt trvale odstraněný (vedle 404). */
    private const HTTP_GONE = 410;

    /**
     * Produkt na stránce; null, když stránka neexistuje (produkt stažený).
     *
     * @throws RetailUnavailable
     * @throws RetailFormatChanged
     */
    public function product(RetailUrl $url): ?RetailProduct
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return null;
        }

        $product = SchemaProductPage::product($html);
        if ($product === null) {
            Log::warning("Obchod {$url->url}: stránka nemá produkt ve schema.org — změnil se formát?");

            throw new RetailFormatChanged((string) __('app.monitoring.retail_format', ['url' => $url->url]));
        }

        return $product;
    }

    /**
     * HTML stránky; null při 404 a 410.
     *
     * @throws RetailUnavailable
     */
    private function fetch(RetailUrl $url): ?string
    {
        try {
            $response = Http::timeout((int) config('games.sources.retail.timeout_seconds'))
                ->withHeaders([
                    'User-Agent' => (string) config('games.sources.retail.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'cs-CZ,cs;q=0.9',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($url->url);
        } catch (ConnectionException $e) {
            Log::warning("Obchod {$url->url} neodpovídá: ".$e->getMessage());

            throw new RetailUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->notFound() || $response->status() === self::HTTP_GONE) {
            return null;
        }

        if (! $response->successful() || preg_match(self::CHALLENGE_PATTERN, $response->body()) === 1) {
            Log::warning("Obchod {$url->url} vrátil HTTP {$response->status()} nebo výzvu ochrany proti robotům.");

            throw new RetailUnavailable((string) __('app.monitoring.retail_blocked', ['url' => $url->url, 'status' => $response->status()]));
        }

        return $response->body();
    }
}
