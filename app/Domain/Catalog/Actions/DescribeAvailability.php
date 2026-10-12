<?php

/**
 * Kde se hry z výsledků hledání prodávají.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Itad\ItadDeal;
use App\Domain\Itad\ItadGamePrices;
use Illuminate\Support\Facades\Cache;

/**
 * Jedním dávkovým dotazem na ceny ITAD (R23) zjistí obchody a nejnižší cenu
 * každé hry z výsledků hledání. Výsledek drží cache jako hledání. Je to jen
 * doplněk — výpadek ITAD (zalogoval ho klient) vrátí prázdný výsledek
 * a hledání se ukáže bez obchodů.
 */
final class DescribeAvailability
{
    private const CACHE_PREFIX = 'catalog:availability:';

    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Obchody (podle názvu, bez opakování) a nejnižší cena podle ITAD ID; hra
     * bez nabídky má prázdný seznam. Null = dostupnost se nepodařilo zjistit.
     *
     * @param  list<string>  $itadIds
     * @return array<string, array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}>|null
     */
    public function handle(array $itadIds): ?array
    {
        if ($itadIds === []) {
            return [];
        }

        $sorted = $itadIds;
        sort($sorted);
        $key = self::CACHE_PREFIX.sha1(implode(',', $sorted));

        /** @var array<string, array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}>|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $prices = $this->itad->prices($itadIds);
        } catch (ItadUnavailable) {
            return null;
        }

        $availability = [];
        foreach ($itadIds as $itadId) {
            $availability[$itadId] = $this->describe($prices[$itadId] ?? null);
        }

        Cache::put($key, $availability, (int) config('games.catalog.search_cache_seconds'));

        return $availability;
    }

    /**
     * Obchody a nejnižší cena jedné hry.
     *
     * @return array{shops: list<string>, bestCents: int|null, bestCurrency: string|null}
     */
    private function describe(?ItadGamePrices $prices): array
    {
        $deals = $prices === null ? [] : $prices->deals;
        usort($deals, fn (ItadDeal $a, ItadDeal $b): int => $a->price->cents <=> $b->price->cents);
        $best = $deals[0] ?? null;

        return [
            'shops' => array_values(array_unique(array_map(fn (ItadDeal $deal): string => $deal->shopName, $deals))),
            'bestCents' => $best?->price->cents,
            'bestCurrency' => $best?->price->currency,
        ];
    }
}
