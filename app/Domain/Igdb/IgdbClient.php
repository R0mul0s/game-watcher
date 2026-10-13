<?php

/**
 * HTTP klient IGDB API — nadcházející hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Igdb;

use App\Domain\Igdb\Exceptions\IgdbUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IGDB (R46) se přihlašuje tokenem aplikace z Twitche (client credentials) —
 * token drží cache, odmítnutý token se jednou obnoví. Dotazy jsou v jazyce
 * Apicalypse (POST s textovým tělem), sestavené jen z konfigurace — nic od
 * uživatele. Klíč aplikace jde jen v těle požadavku na token, nikdy do logu
 * ani do zprávy výjimky.
 */
final class IgdbClient
{
    private const GAMES_PATH = '/games';

    private const TOKEN_CACHE_KEY = 'igdb:token';

    private const SECONDS_PER_DAY = 86400;

    /** Údaje hry pro seznam nadcházejících her (obrázky, žánry a vydání rozbalené). */
    private const GAME_FIELDS = [
        'name', 'url', 'hypes', 'game_modes',
        'cover.image_id', 'artworks.image_id', 'screenshots.image_id', 'genres.name',
        'multiplayer_modes.offlinecoop', 'multiplayer_modes.splitscreen',
        'release_dates.date', 'release_dates.platform', 'release_dates.date_format', 'release_dates.status',
        'external_games.external_game_source', 'external_games.category', 'external_games.uid',
    ];

    /**
     * Je klient nastavený? Bez klíče aplikace (vývoj, produkce před nastavením)
     * se seznam nestahuje.
     */
    public function isConfigured(): bool
    {
        return (string) config('games.sources.igdb.client_id') !== '' && (string) config('games.sources.igdb.client_secret') !== '';
    }

    /**
     * Hry vycházející na hlídaných platformách do upcoming.window_days dní,
     * od nejpopulárnější. Hra bez nadcházejícího vydání (na platformě už vyšla)
     * má prázdné `releases`.
     *
     * @return list<IgdbGame>
     *
     * @throws IgdbUnavailable
     */
    public function upcoming(CarbonImmutable $now): array
    {
        $until = $now->addDays((int) config('games.upcoming.window_days'));
        $data = $this->query(self::GAMES_PATH, $this->upcomingQuery($now, $until));

        $games = [];
        foreach ($data as $item) {
            $game = is_array($item) ? IgdbGame::fromApi($item, $now, $until) : null;
            if ($game === null) {
                throw $this->formatChanged(self::GAMES_PATH);
            }
            $games[] = $game;
        }

        return $games;
    }

    /**
     * Dotaz Apicalypse na nadcházející hry — vydání na hlídané platformě v okně,
     * hlavní hry, remaky a remastery (bez edic), bez zrušených her a nad prahem
     * popularity. Datum se znovu kontroluje po platformách v IgdbGame.
     */
    private function upcomingQuery(CarbonImmutable $now, CarbonImmutable $until): string
    {
        $list = fn (array $values): string => '('.implode(',', array_map(intval(...), $values)).')';
        /** @var array<string, int> $platforms */
        $platforms = config('games.upcoming.platforms');
        /** @var list<int> $gameTypes */
        $gameTypes = config('games.upcoming.game_types');
        /** @var list<int> $skippedStatuses */
        $skippedStatuses = config('games.upcoming.skipped_game_statuses');

        return 'fields '.implode(',', self::GAME_FIELDS).';'
            .' where release_dates.platform = '.$list(array_values($platforms))
            .' & release_dates.date > '.$now->getTimestamp()
            .' & release_dates.date < '.$until->getTimestamp()
            .' & game_type = '.$list($gameTypes)
            .' & hypes >= '.(int) config('games.upcoming.min_hypes')
            .' & version_parent = null'
            .' & game_status != '.$list($skippedStatuses).';'
            .' sort hypes desc;'
            .' limit '.(int) config('games.upcoming.limit').';';
    }

    /**
     * Dotaz na endpoint IGDB; odmítnutý token jednou obnoví.
     *
     * @return list<mixed>
     *
     * @throws IgdbUnavailable
     */
    private function query(string $path, string $body): array
    {
        $response = $this->post($path, $body, $this->token());
        if ($response->unauthorized()) {
            // Token zneplatněný dřív, než vypršel (nový secret aplikace)
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->post($path, $body, $this->token());
        }

        if (! $response->successful()) {
            Log::warning("IGDB {$path} vrátil HTTP {$response->status()}.");

            throw new IgdbUnavailable("HTTP {$response->status()}");
        }

        $data = $response->json();

        return is_array($data) && array_is_list($data) ? $data : throw $this->formatChanged($path);
    }

    /**
     * POST dotazu s tokenem a ID aplikace.
     *
     * @throws IgdbUnavailable
     */
    private function post(string $path, string $body, string $token): Response
    {
        try {
            return Http::baseUrl((string) config('games.sources.igdb.url'))
                ->withHeaders(['Client-ID' => (string) config('games.sources.igdb.client_id')])
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('games.sources.igdb.timeout_seconds'))
                ->withBody($body, 'text/plain')
                ->post($path);
        } catch (ConnectionException $e) {
            Log::warning("IGDB {$path} neodpovídá: ".$e->getMessage());

            throw new IgdbUnavailable($e->getMessage(), previous: $e);
        }
    }

    /**
     * Token aplikace z cache, jinak nový z Twitche. Cache vyprší
     * o token_margin_seconds dřív než token.
     *
     * @throws IgdbUnavailable
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        try {
            // Klíč aplikace jen v těle požadavku — adresa se objevuje v chybách a logu
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('games.sources.igdb.timeout_seconds'))
                ->post((string) config('games.sources.igdb.token_url'), [
                    'client_id' => (string) config('games.sources.igdb.client_id'),
                    'client_secret' => (string) config('games.sources.igdb.client_secret'),
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Twitch (token IGDB) neodpovídá: '.$e->getMessage());

            throw new IgdbUnavailable($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            Log::warning("Twitch odmítl klíč aplikace IGDB: HTTP {$response->status()}.");

            throw new IgdbUnavailable((string) __('app.monitoring.igdb_token', ['status' => $response->status()]));
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in');
        if (! is_string($token) || $token === '' || ! is_int($expiresIn)) {
            throw $this->formatChanged('token');
        }

        $margin = (int) config('games.sources.igdb.token_margin_seconds');
        // Token platí ~60 dní; kdyby platil kratší dobu než rezerva, aspoň den
        Cache::put(self::TOKEN_CACHE_KEY, $token, $expiresIn > $margin ? $expiresIn - $margin : min($expiresIn, self::SECONDS_PER_DAY));

        return $token;
    }

    /**
     * Zaloguje a vrátí výjimku pro odpověď bez očekávaných dat.
     */
    private function formatChanged(string $path): IgdbUnavailable
    {
        Log::warning("IGDB {$path}: odpověď nemá očekávaná data — změnil se formát?");

        return new IgdbUnavailable((string) __('app.monitoring.igdb_format', ['path' => $path]));
    }
}
