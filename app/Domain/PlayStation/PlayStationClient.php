<?php

/**
 * Stahování stránek PlayStation Store.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation;

use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Česká verze stránek (ceny v CZK). Akamai PS Store odmítá klienty bez hlaviček
 * běžného prohlížeče — PHP cURL s nimi z hostingu projde (průzkum R7).
 * Stahuje se po jednom produktu s pauzou (games.sources.playstation.request_delay_ms).
 */
final class PlayStationClient
{
    /** ID konceptu v meta značce stránky hry na www.playstation.com (R24). */
    private const GAME_PAGE_CONCEPT_META_PATTERN = '~<meta\s+name="conceptId"\s+content="(\d{1,12})"~';

    /**
     * Produkt s cenami; null, když ho obchod nezná (404 nebo stránka bez produktu).
     *
     * @throws PlayStationUnavailable
     */
    public function product(string $productId): ?PlayStationProduct
    {
        $path = 'product/'.$productId;
        $html = $this->page($path);

        return $html === null ? null : $this->parse($path, $html)->product($productId);
    }

    /**
     * Výchozí produkt konceptu (stránky hry); null, když koncept neexistuje.
     *
     * @throws PlayStationUnavailable
     */
    public function conceptDefaultProductId(string $conceptId): ?string
    {
        $path = 'concept/'.$conceptId;
        $html = $this->page($path);

        return $html === null ? null : $this->parse($path, $html)->defaultProductId($conceptId);
    }

    /**
     * Produkt ze stránky hry na www.playstation.com (R22) — první odkaz do obchodu
     * (standardní edice), případně výchozí produkt odkazovaného konceptu nebo
     * konceptu z meta značky (R24); null, když stránka nemá ani jedno.
     *
     * @throws PlayStationUnavailable
     */
    public function gamePageProductId(string $slug): ?string
    {
        $html = $this->fetch($this->gamePageUrl($slug));
        if ($html === null) {
            return null;
        }

        $storeLink = '~https?://'.preg_quote((string) config('games.sources.playstation.host'), '~').'/[a-z-]+/';
        if (preg_match($storeLink.'product/('.PlayStationUrl::PRODUCT_ID_PATTERN.')~', $html, $match) === 1) {
            return $match[1];
        }

        // Některé stránky (Until Dawn) odkaz do obchodu v HTML nemají, jen ID
        // konceptu v meta značce
        return preg_match($storeLink.'concept/(\d{1,12})~', $html, $match) === 1
            || preg_match(self::GAME_PAGE_CONCEPT_META_PATTERN, $html, $match) === 1
            ? $this->conceptDefaultProductId($match[1])
            : null;
    }

    /**
     * Existují stránky her na www.playstation.com (R24)? Dotazy HEAD běží
     * souběžně (games.sources.playstation.game_page_concurrency) — jen stavový
     * kód, bez stahování stránky. true = 200, false = 404, null = nezjištěno
     * (výpadek, jiný kód) — výpadek hledání nezastaví.
     *
     * @param  list<string>  $slugs
     * @return array<string, bool|null>
     */
    public function gamePagesExist(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($slugs): void {
            foreach ($slugs as $slug) {
                $pool->as($slug)
                    ->timeout((int) config('games.sources.playstation.timeout_seconds'))
                    ->withHeaders(['User-Agent' => (string) config('games.sources.playstation.user_agent')])
                    ->withOptions(['allow_redirects' => false])
                    ->head($this->gamePageUrl($slug));
            }
        }, max(0, (int) config('games.sources.playstation.game_page_concurrency')));

        $exists = [];
        foreach ($slugs as $slug) {
            $response = $responses[$slug] ?? null;
            $exists[$slug] = match (true) {
                $response instanceof Response && $response->ok() => true,
                $response instanceof Response && $response->notFound() => false,
                default => null,
            };
        }

        return $exists;
    }

    /**
     * Adresa stránky hry na www.playstation.com v české verzi.
     */
    public function gamePageUrl(string $slug): string
    {
        return rtrim((string) config('games.sources.playstation.site_url'), '/').'/'.config('games.sources.playstation.locale').'/games/'.$slug.'/';
    }

    /**
     * Veřejná adresa produktu v české verzi obchodu.
     */
    public function productUrl(string $productId): string
    {
        return $this->baseUrl().'/product/'.$productId;
    }

    /**
     * Rozbor stránky obchodu. Stránka bez dat obchodu znamená změněný formát —
     * výpadek, ne „produkt zmizel“, jinak by cron ceny všech produktů smazal (R29).
     *
     * @throws PlayStationUnavailable
     */
    private function parse(string $path, string $html): PlayStationPage
    {
        $page = PlayStationPage::fromHtml($html);
        if (! $page->hasData()) {
            Log::warning("PlayStation Store {$path}: stránka nemá data obchodu (JSON skripty env:*) — změnil se formát?");

            throw new PlayStationUnavailable((string) __('app.monitoring.playstation_format', ['path' => $path]));
        }

        return $page;
    }

    /**
     * HTML stránky; null při 404.
     *
     * @throws PlayStationUnavailable
     */
    private function page(string $path): ?string
    {
        return $this->fetch($this->baseUrl().'/'.$path);
    }

    /**
     * HTML stránky z PS Store nebo www.playstation.com (adresu skládá jen tahle
     * třída z konfigurace); null při 404.
     *
     * @throws PlayStationUnavailable
     */
    private function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout((int) config('games.sources.playstation.timeout_seconds'))
                ->withHeaders([
                    'User-Agent' => (string) config('games.sources.playstation.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'cs-CZ,cs;q=0.9',
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            Log::warning("PlayStation Store {$url} neodpovídá: ".$e->getMessage());

            throw new PlayStationUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning("PlayStation Store {$url} vrátil HTTP {$response->status()}.");

            throw new PlayStationUnavailable("HTTP {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Adresa obchodu s jazykem: https://store.playstation.com/cs-cz.
     */
    private function baseUrl(): string
    {
        return rtrim((string) config('games.sources.playstation.url'), '/').'/'.config('games.sources.playstation.locale');
    }
}
