<?php

/**
 * HTTP klient IsThereAnyDeal API.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Katalog her (R10) a později ceny oficiálních obchodů (R9). Klíč jde
 * v hlavičce ITAD-API-Key, aby se adresa požadavku s chybou mohla bez obav
 * zalogovat. Každá chyba služby skončí výjimkou ItadUnavailable.
 */
final class ItadClient
{
    private const SEARCH_PATH = '/games/search/v1';

    private const LOOKUP_PATH = '/games/lookup/v1';

    private const INFO_PATH = '/games/info/v2';

    private const PRICES_PATH = '/games/prices/v3';

    private const HISTORY_PATH = '/games/history/v2';

    private const DEALS_PATH = '/deals/v2';

    private const SUBSCRIPTIONS_PATH = '/games/subs/v1';

    private const BUNDLES_PATH = '/bundles/v1';

    /**
     * Hry podle názvu, v pořadí relevance podle ITAD.
     *
     * @return list<ItadGame>
     *
     * @throws ItadUnavailable
     */
    public function search(string $title, int $limit): array
    {
        $data = $this->get(self::SEARCH_PATH, ['title' => $title, 'results' => $limit]);
        if (! array_is_list($data)) {
            throw $this->unexpectedShape(self::SEARCH_PATH);
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?ItadGame => is_array($item) ? ItadGame::fromApi($item) : null,
            $data,
        )));
    }

    /**
     * Hra podle Steam App ID; null, když ji ITAD nezná.
     *
     * @throws ItadUnavailable
     */
    public function lookupSteamApp(int $appId): ?ItadGame
    {
        return $this->lookup(['appid' => $appId]);
    }

    /**
     * Hra s přesně tímto názvem (bez ohledu na velikost písmen); null, když ji
     * ITAD nezná. Najde i záznamy, které `games/search` vynechává — exkluzivity
     * konzolí bez obchodů a obrázků (R22).
     *
     * @throws ItadUnavailable
     */
    public function lookupTitle(string $title): ?ItadGame
    {
        return $this->lookup(['title' => $title]);
    }

    /**
     * Přesné vyhledání jedné hry (`games/lookup`) podle Steam App ID nebo názvu.
     *
     * @param  array<string, string|int>  $query
     *
     * @throws ItadUnavailable
     */
    private function lookup(array $query): ?ItadGame
    {
        $data = $this->get(self::LOOKUP_PATH, $query);
        if (($data['found'] ?? null) !== true) {
            return null;
        }

        return is_array($data['game'] ?? null)
            ? (ItadGame::fromApi($data['game']) ?? throw $this->unexpectedShape(self::LOOKUP_PATH))
            : throw $this->unexpectedShape(self::LOOKUP_PATH);
    }

    /**
     * Podrobné údaje hry; null, když hra s tímto ID neexistuje.
     *
     * @throws ItadUnavailable
     */
    public function info(string $itadId): ?ItadGameInfo
    {
        $data = $this->get(self::INFO_PATH, ['id' => $itadId], allowNotFound: true);
        if ($data === null) {
            return null;
        }

        return ItadGameInfo::fromApi($data) ?? throw $this->unexpectedShape(self::INFO_PATH);
    }

    /**
     * Historie cen hry v obchodech od data (R28) — změny ceny po obchodech,
     * první záznam obchodu je stav k začátku období. Hra bez historie = prázdný seznam.
     *
     * @return list<ItadPriceChange>
     *
     * @throws ItadUnavailable
     */
    public function history(string $itadId, CarbonImmutable $since): array
    {
        $data = $this->get(self::HISTORY_PATH, [
            'id' => $itadId,
            'country' => config('games.sources.itad.country'),
            'since' => $since->utc()->toIso8601ZuluString(),
        ], allowNotFound: true) ?? [];

        if (! array_is_list($data)) {
            throw $this->unexpectedShape(self::HISTORY_PATH);
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?ItadPriceChange => is_array($item) ? ItadPriceChange::fromApi($item) : null,
            $data,
        )));
    }

    /**
     * Seznam her ve slevě (R43) — nejlepší aktuální nabídka každé hry v zemi
     * z konfigurace. Filtr je JSON podle dokumentace ITAD (štítky, druh, značka
     * minima); řazení hodnotou ITAD (`rank`, `-cut`…).
     *
     * @param  array<string, mixed>  $filter
     * @return list<ItadDealListing>
     *
     * @throws ItadUnavailable
     */
    public function deals(array $filter, string $sort, int $limit): array
    {
        $data = $this->get(self::DEALS_PATH, [
            'country' => (string) config('games.sources.itad.country'),
            'limit' => $limit,
            'sort' => $sort,
            'filter' => (string) json_encode($filter, JSON_THROW_ON_ERROR),
        ]);

        if (! is_array($data['list'] ?? null) || ! array_is_list($data['list'])) {
            throw $this->unexpectedShape(self::DEALS_PATH);
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?ItadDealListing => is_array($item) ? ItadDealListing::fromApi($item) : null,
            $data['list'],
        )));
    }

    /**
     * Aktuální ceny her v zemi z konfigurace (R9) — jeden požadavek na dávku
     * až games.prices.itad_batch_size her. Hry, které ITAD nezná, ve výsledku chybí.
     *
     * @param  list<string>  $itadIds
     * @return array<string, ItadGamePrices> podle ITAD ID
     *
     * @throws ItadUnavailable
     */
    public function prices(array $itadIds): array
    {
        $data = $this->postIds(self::PRICES_PATH, $itadIds);

        $prices = [];
        foreach ($data as $item) {
            $gamePrices = is_array($item) ? ItadGamePrices::fromApi($item) : null;
            if ($gamePrices !== null) {
                $prices[$gamePrices->itadId] = $gamePrices;
            }
        }

        return $prices;
    }

    /**
     * Předplatná her v zemi z konfigurace (R50) — dávka jako u cen. Hra bez
     * předplatného v odpovědi chybí; ve výsledku má prázdný seznam.
     *
     * @param  list<string>  $itadIds
     * @return array<string, list<ItadSubscription>> podle ITAD ID — všechny hry z dotazu
     *
     * @throws ItadUnavailable
     */
    public function subscriptions(array $itadIds): array
    {
        $data = $this->postIds(self::SUBSCRIPTIONS_PATH, $itadIds);

        $subscriptions = array_fill_keys($itadIds, []);
        foreach ($data as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (! is_string($id) || ! is_array($item['subs'] ?? null)) {
                continue;
            }

            $subscriptions[$id] = array_values(array_filter(array_map(
                fn (mixed $sub): ?ItadSubscription => is_array($sub) ? ItadSubscription::fromApi($sub) : null,
                $item['subs'],
            )));
        }

        return $subscriptions;
    }

    /**
     * Aktivní bundly v zemi z konfigurace (R51) — po stránkách, dokud je
     * stránka plná (nejvýš games.sources.itad.bundles_max_pages); bez bundlů pro dospělé.
     *
     * @return list<ItadBundle>
     *
     * @throws ItadUnavailable
     */
    public function bundles(): array
    {
        $pageSize = (int) config('games.sources.itad.bundles_page_size');
        $bundles = [];

        for ($page = 0; $page < (int) config('games.sources.itad.bundles_max_pages'); $page++) {
            $data = $this->get(self::BUNDLES_PATH, [
                'country' => (string) config('games.sources.itad.country'),
                'limit' => $pageSize,
                'offset' => $page * $pageSize,
            ]);
            if (! array_is_list($data)) {
                throw $this->unexpectedShape(self::BUNDLES_PATH);
            }

            foreach ($data as $item) {
                $bundle = is_array($item) ? ItadBundle::fromApi($item) : null;
                if ($bundle !== null) {
                    $bundles[] = $bundle;
                }
            }

            if (count($data) < $pageSize) {
                break;
            }
        }

        return $bundles;
    }

    /**
     * POST se seznamem ITAD ID (dávkové endpointy); vrátí seznam z odpovědi.
     *
     * @param  list<string>  $itadIds
     * @return list<mixed>
     *
     * @throws ItadUnavailable
     */
    private function postIds(string $path, array $itadIds): array
    {
        try {
            $response = $this->request()->post($path.'?'.http_build_query(['country' => config('games.sources.itad.country')]), $itadIds);
        } catch (ConnectionException $e) {
            Log::warning("IsThereAnyDeal {$path} neodpovídá: ".$e->getMessage());

            throw new ItadUnavailable($e->getMessage(), previous: $e);
        }

        $data = $this->decode($response, $path);

        return array_is_list($data) ? $data : throw $this->unexpectedShape($path);
    }

    /**
     * GET na API; vrátí dekódovaný JSON, při 404 (je-li dovolena) null.
     *
     * @param  array<string, string|int>  $query
     * @return ($allowNotFound is true ? array<mixed>|null : array<mixed>)
     *
     * @throws ItadUnavailable
     */
    private function get(string $path, array $query, bool $allowNotFound = false): ?array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $e) {
            Log::warning("IsThereAnyDeal {$path} neodpovídá: ".$e->getMessage());

            throw new ItadUnavailable($e->getMessage(), previous: $e);
        }

        if ($allowNotFound && $response->notFound()) {
            return null;
        }

        return $this->decode($response, $path);
    }

    /**
     * JSON úspěšné odpovědi; chybový stav nebo jiný obsah než JSON = výjimka.
     *
     * @return array<mixed>
     *
     * @throws ItadUnavailable
     */
    private function decode(Response $response, string $path): array
    {
        if (! $response->successful()) {
            Log::warning("IsThereAnyDeal {$path} vrátil HTTP {$response->status()}: ".$this->reason($response));

            throw new ItadUnavailable("HTTP {$response->status()}");
        }

        $data = $response->json();

        return is_array($data) ? $data : throw $this->unexpectedShape($path);
    }

    /**
     * Požadavek s adresou, klíčem a timeoutem z konfigurace.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('games.sources.itad.url'))
            ->withHeaders(['ITAD-API-Key' => (string) config('games.sources.itad.key')])
            ->acceptJson()
            ->timeout((int) config('games.sources.itad.timeout_seconds'));
    }

    /**
     * Důvod chyby z těla odpovědi ITAD (`reason_phrase`) — bez celého těla v logu.
     */
    private function reason(Response $response): string
    {
        $reason = $response->json('reason_phrase');

        return is_string($reason) ? $reason : '';
    }

    /**
     * Zaloguje a vrátí výjimku pro odpověď, která nemá očekávaný tvar.
     */
    private function unexpectedShape(string $path): ItadUnavailable
    {
        Log::warning("IsThereAnyDeal {$path} vrátil neočekávaný tvar dat.");

        return new ItadUnavailable("Neočekávaný tvar odpovědi {$path}");
    }
}
