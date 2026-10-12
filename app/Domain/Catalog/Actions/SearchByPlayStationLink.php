<?php

/**
 * Hledání hry podle odkazu z PlayStation Store.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\GameQuery;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadGame;
use App\Domain\PlayStation\Actions\FindPlayStationProduct;
use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Domain\PlayStation\PlayStationClient;
use Illuminate\Support\Facades\Cache;

/**
 * Do „Přidat hru“ jde vložit odkaz na PS Store nebo na stránku hry na
 * playstation.com (R24). Ze stránky produktu se vezme název a podle něj se
 * hledá v ITAD jako jindy (i přesný název, R22) — hra zůstává pod ITAD ID
 * (R10). Název produktu drží cache jako hledání, takže opakované hledání
 * obchod nevolá. Výsledek nese odkaz na produkt, který se při přidání připojí.
 */
final class SearchByPlayStationLink
{
    private const CACHE_PREFIX = 'catalog:playstation:';

    public function __construct(
        private readonly FindPlayStationProduct $findProduct,
        private readonly PlayStationClient $client,
        private readonly SearchGames $searchGames,
    ) {}

    /**
     * Produkt z odkazu (název, odkaz na produkt) a hry z katalogu k němu.
     *
     * @return array{product: array{name: string, url: string}, games: list<ItadGame>}
     *
     * @throws PlayStationProductNotFound
     * @throws PlayStationUnavailable
     * @throws ItadUnavailable
     */
    public function handle(string $url): array
    {
        $key = self::CACHE_PREFIX.sha1($url);

        /** @var array{name: string, url: string, searchTitle: string}|null $product */
        $product = Cache::get($key);
        if (! is_array($product)) {
            $found = $this->findProduct->handle($url);
            $product = [
                'name' => $found->name,
                'url' => $this->client->productUrl($found->id),
                'searchTitle' => $found->searchTitle(),
            ];
            Cache::put($key, $product, (int) config('games.catalog.search_cache_seconds'));
        }

        return [
            'product' => ['name' => $product['name'], 'url' => $product['url']],
            'games' => $this->searchGames->handle(GameQuery::title($product['searchTitle'])),
        ];
    }
}
