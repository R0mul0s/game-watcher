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
        $data = $this->get(self::LOOKUP_PATH, ['appid' => $appId]);
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
        $path = self::PRICES_PATH.'?'.http_build_query(['country' => config('games.sources.itad.country')]);

        try {
            $response = $this->request()->post($path, $itadIds);
        } catch (ConnectionException $e) {
            Log::warning('IsThereAnyDeal '.self::PRICES_PATH.' neodpovídá: '.$e->getMessage());

            throw new ItadUnavailable($e->getMessage(), previous: $e);
        }

        $data = $this->decode($response, self::PRICES_PATH);
        if (! array_is_list($data)) {
            throw $this->unexpectedShape(self::PRICES_PATH);
        }

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
