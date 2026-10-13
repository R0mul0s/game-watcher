<?php

/**
 * Hledání a stránky her na HowLongToBeat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb;

use App\Domain\Hltb\Exceptions\HltbUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HowLongToBeat nemá veřejné API (R44) — klient dělá totéž co jejich web:
 * hledání POST /api/search/site s tokenem z /api/search/site/init (token je
 * vázaný na IP a User-Agent, proto vždy IPv4 a stejný User-Agent; drží ho
 * cache), stránka hry /game/<id> má data ve skriptu `__NEXT_DATA__`. Odmítnutý
 * token se jednou obnoví. Každá chyba nebo jiný tvar dat = HltbUnavailable —
 * formát se může kdykoli změnit, nikdy se neuloží odhad.
 */
final class HltbClient
{
    private const INIT_PATH = '/api/search/site/init';

    private const SEARCH_PATH = '/api/search/site';

    private const TOKEN_CACHE_KEY = 'hltb:search-token';

    /** Parametr `t` adresy tokenu — čas v milisekundách jako na webu. */
    private const MILLISECONDS = 1000;

    /** Výsledků na stránku hledání (jako web). */
    private const SEARCH_PAGE_SIZE = 20;

    /** Data stránky hry vložená Next.js. */
    private const NEXT_DATA_PATTERN = '~<script id="__NEXT_DATA__" type="application/json">(.*?)</script>~s';

    /**
     * Hry podle názvu v pořadí HLTB (od nejoblíbenější).
     *
     * @return list<HltbGame>
     *
     * @throws HltbUnavailable
     */
    public function search(string $title): array
    {
        $terms = preg_split('/\s+/u', trim($title), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $response = $this->postSearch($terms, $this->token());
        if ($response->forbidden()) {
            // Token vypršel — web ho v tu chvíli obnoví a zkusí znovu
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->postSearch($terms, $this->token());
        }

        $data = $this->json($response, self::SEARCH_PATH);
        if (! is_array($data['data'] ?? null) || ! array_is_list($data['data'])) {
            throw $this->formatChanged(self::SEARCH_PATH);
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?HltbGame => is_array($item) ? HltbGame::fromApi($item) : null,
            $data['data'],
        )));
    }

    /**
     * Hra podle ID ze stránky hry; null, když ji HLTB nezná.
     *
     * @throws HltbUnavailable
     */
    public function game(int $id): ?HltbGame
    {
        $path = '/game/'.$id;
        $response = $this->send(fn (PendingRequest $request): Response => $request
            ->withOptions(['allow_redirects' => false])
            ->accept('text/html')
            ->get($path), $path);

        if ($response->notFound() || $response->redirect()) {
            return null;
        }

        if (! $response->successful()) {
            throw $this->failed($response, $path);
        }

        // Stránka bez dat hry je změna formátu, ne „hra neexistuje“ (R29)
        $games = preg_match(self::NEXT_DATA_PATTERN, $response->body(), $match) === 1
            ? data_get(json_decode($match[1], true), 'props.pageProps.game.data.game')
            : null;
        if (! is_array($games)) {
            throw $this->formatChanged($path);
        }

        foreach ($games as $item) {
            if (is_array($item) && ($item['game_id'] ?? null) === $id) {
                return HltbGame::fromApi($item) ?? throw $this->formatChanged($path);
            }
        }

        throw $this->formatChanged($path);
    }

    /**
     * Token hledání z cache, jinak nový z /api/search/site/init.
     *
     * @throws HltbUnavailable
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $response = $this->send(fn (PendingRequest $request): Response => $request
            ->acceptJson()
            ->get(self::INIT_PATH, ['t' => (int) (microtime(true) * self::MILLISECONDS)]), self::INIT_PATH);
        $token = $this->json($response, self::INIT_PATH)['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw $this->formatChanged(self::INIT_PATH);
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, (int) config('games.sources.hltb.token_cache_seconds'));

        return $token;
    }

    /**
     * Hledání s tokenem — tělo jako z webu HLTB (jen hry, bez filtrů).
     *
     * @param  list<string>  $terms
     *
     * @throws HltbUnavailable
     */
    private function postSearch(array $terms, string $token): Response
    {
        return $this->send(fn (PendingRequest $request): Response => $request
            ->acceptJson()
            ->withHeaders(['x-auth-token' => $token, 'Origin' => (string) config('games.sources.hltb.url')])
            ->post(self::SEARCH_PATH, [
                'searchType' => 'games',
                'searchTerms' => $terms,
                'searchPage' => 1,
                'size' => self::SEARCH_PAGE_SIZE,
                'searchOptions' => [
                    'games' => [
                        'userId' => 0,
                        'platform' => '',
                        'sortCategory' => 'popular',
                        'rangeCategory' => 'main',
                        'rangeTime' => ['min' => null, 'max' => null],
                        'gameplay' => ['perspective' => '', 'flow' => '', 'genre' => '', 'difficulty' => ''],
                        'rangeYear' => ['min' => '', 'max' => ''],
                        'modifier' => '',
                    ],
                    'users' => ['sortCategory' => 'postcount'],
                    'lists' => ['sortCategory' => 'follows'],
                    'filter' => '',
                    'sort' => 0,
                    'randomizer' => 0,
                ],
                'useCache' => true,
            ]), self::SEARCH_PATH);
    }

    /**
     * Pošle požadavek s adresou, hlavičkami prohlížeče a jen přes IPv4.
     *
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws HltbUnavailable
     */
    private function send(callable $send, string $path): Response
    {
        $url = rtrim((string) config('games.sources.hltb.url'), '/');

        try {
            return $send(Http::baseUrl($url)
                ->timeout((int) config('games.sources.hltb.timeout_seconds'))
                // Token je vázaný na IP — všechny požadavky stejnou cestou
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->withHeaders([
                    'User-Agent' => (string) config('games.sources.hltb.user_agent'),
                    'Referer' => $url.'/',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ]));
        } catch (ConnectionException $e) {
            Log::warning("HowLongToBeat {$path} neodpovídá: ".$e->getMessage());

            throw new HltbUnavailable($e->getMessage(), previous: $e);
        }
    }

    /**
     * JSON úspěšné odpovědi.
     *
     * @return array<mixed>
     *
     * @throws HltbUnavailable
     */
    private function json(Response $response, string $path): array
    {
        if (! $response->successful()) {
            throw $this->failed($response, $path);
        }

        $data = $response->json();

        return is_array($data) ? $data : throw $this->formatChanged($path);
    }

    /**
     * Zaloguje a vrátí výjimku pro chybový stav HTTP.
     */
    private function failed(Response $response, string $path): HltbUnavailable
    {
        Log::warning("HowLongToBeat {$path} vrátil HTTP {$response->status()}.");

        return new HltbUnavailable("HTTP {$response->status()}");
    }

    /**
     * Zaloguje a vrátí výjimku pro odpověď bez očekávaných dat.
     */
    private function formatChanged(string $path): HltbUnavailable
    {
        Log::warning("HowLongToBeat {$path}: odpověď nemá očekávaná data — změnil se formát?");

        return new HltbUnavailable((string) __('app.monitoring.hltb_format', ['path' => $path]));
    }
}
