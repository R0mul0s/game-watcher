<?php

/**
 * Ceny hry pro stránky — převedené do měny zobrazení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Currency\ExchangeRates;
use App\Enums\PriceScope;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;

/**
 * Převádí a řadí server, ne Vue (docs/CODING_GUIDELINES.md, sekce 5). Každá
 * cena jde jako `price` v měně zobrazení a `original` v měně obchodu, když se
 * liší (R14); bez kurzu zůstane v měně obchodu. Hra musí mít načtené vazby
 * currentPrices a priceLows (žádné dotazy v cyklu přes karty přehledu).
 */
final class PriceBoard
{
    public function __construct(private readonly ExchangeRates $rates) {}

    /**
     * Souhrn pro kartu na přehledu — nejlevnější oficiální obchod a obchody s klíči.
     *
     * @return array{best: array<string, mixed>|null, keyshops: array<string, mixed>|null}
     */
    public function summary(Game $game, string $currency): array
    {
        $offers = $this->offers($game, $currency);

        return [
            'best' => $offers[0] ?? null,
            'keyshops' => $this->keyshops($game, $currency),
        ];
    }

    /**
     * Všechny ceny pro detail hry.
     *
     * @return array<string, mixed>
     */
    public function detail(Game $game, string $currency): array
    {
        $latest = $game->currentPrices->sortByDesc('fetched_at')->first();
        $fetchedAt = $latest instanceof CurrentPrice ? $latest->fetched_at : null;

        return [
            'offers' => $this->offers($game, $currency),
            'keyshops' => $this->keyshops($game, $currency),
            'lows' => [
                'official' => $this->low($game, PriceScope::Official, $currency),
                'keyshops' => $this->low($game, PriceScope::Keyshops, $currency),
            ],
            'fetchedAt' => $fetchedAt?->toIso8601String(),
        ];
    }

    /**
     * Nabídky oficiálních obchodů od nejlevnější (po převodu do měny zobrazení).
     *
     * @return list<array<string, mixed>>
     */
    private function offers(Game $game, string $currency): array
    {
        $offers = $game->currentPrices
            ->where('source', PriceSource::Itad)
            ->map(function (CurrentPrice $price) use ($currency): array {
                $converted = $this->converted($price->price(), $currency);

                return [
                    'shop' => $price->shop_name,
                    ...$converted,
                    'regular' => ($regular = $price->regularPrice()) === null ? null : $this->converted($regular, $currency)['price'],
                    'cut' => $price->cut,
                    'drm' => $price->drm,
                    'url' => $price->url,
                    'expiresAt' => $price->expires_at?->toIso8601String(),
                    'sortCents' => $converted['sortCents'],
                ];
            })
            ->sortBy([['sortCents', 'asc'], ['shop', 'asc']])
            ->values()
            ->all();

        return array_values(array_map(function (array $offer): array {
            unset($offer['sortCents']);

            return $offer;
        }, $offers));
    }

    /**
     * Nejnižší cena obchodů s klíči (gg.deals) s povinným odkazem na zdroj.
     *
     * @return array<string, mixed>|null
     */
    private function keyshops(Game $game, string $currency): ?array
    {
        $price = $game->currentPrices->firstWhere('shop_key', CurrentPrice::KEYSHOPS_KEY);
        if (! $price instanceof CurrentPrice) {
            return null;
        }

        $converted = $this->converted($price->price(), $currency);

        return [
            'price' => $converted['price'],
            'original' => $converted['original'],
            'url' => $price->url ?? config('games.sources.ggdeals.site_url'),
        ];
    }

    /**
     * Historické minimum ze zdroje.
     *
     * @return array{price: array{amount: float, currency: string}, original: array{amount: float, currency: string}|null}|null
     */
    private function low(Game $game, PriceScope $scope, string $currency): ?array
    {
        $low = $game->priceLows->firstWhere('scope', $scope);
        if (! $low instanceof PriceLow) {
            return null;
        }

        $converted = $this->converted($low->price(), $currency);

        return ['price' => $converted['price'], 'original' => $converted['original']];
    }

    /**
     * Cena v měně zobrazení a původní cena, když se měna liší.
     *
     * @return array{price: array{amount: float, currency: string}, original: array{amount: float, currency: string}|null, sortCents: int}
     */
    private function converted(Money $money, string $currency): array
    {
        $converted = $this->rates->convert($money, $currency);
        if ($converted === null || $converted->currency === $money->currency) {
            return ['price' => $money->toArray(), 'original' => null, 'sortCents' => ($converted ?? $money)->cents];
        }

        return ['price' => $converted->toArray(), 'original' => $money->toArray(), 'sortCents' => $converted->cents];
    }
}
