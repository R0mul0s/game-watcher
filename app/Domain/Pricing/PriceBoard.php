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
use App\Enums\Platform;
use App\Enums\PriceBasis;
use App\Enums\PriceScope;
use App\Enums\PriceSource;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\PriceLow;

/**
 * Převádí a řadí server, ne Vue (docs/CODING_GUIDELINES.md, sekce 5). Každá
 * cena jde jako `price` v měně zobrazení a `original` v měně obchodu, když se
 * liší (R14); bez kurzu zůstane v měně obchodu. Oficiální obchody i produkty
 * PlayStation Store jsou v jednom výpisu jako samostatné položky (R20), obchody
 * s klíči zvlášť. Hra musí mít načtené vazby currentPrices a priceLows (žádné
 * dotazy v cyklu přes karty přehledu).
 */
final class PriceBoard
{
    public function __construct(private readonly ExchangeRates $rates) {}

    /**
     * Souhrn pro kartu na přehledu — nejlevnější nabídka podle filtru, nejlevnější
     * produkt PlayStation Store a obchody s klíči.
     *
     * @return array{best: array<string, mixed>|null, playstation: array<string, mixed>|null, keyshops: array<string, mixed>|null}
     */
    public function summary(Game $game, string $currency, PriceFilter $filter): array
    {
        $offers = $this->offers($game, $currency, $filter);
        $playstation = array_values(array_filter($offers, fn (array $offer): bool => $offer['platform'] === Platform::PlayStation->value));

        return [
            'best' => $offers[0] ?? null,
            'playstation' => $playstation[0] ?? null,
            'keyshops' => $filter->acceptsKeyshops() ? $this->keyshops($game, $currency) : null,
        ];
    }

    /**
     * Nejlevnější nabídka souhrnu včetně obchodů s klíči (upozornění, slevy
     * dne — R26); nabídka s klíči dostane klíč obchodu, ať jde dohledat zdroj.
     *
     * @param  array{best: array<string, mixed>|null, playstation: array<string, mixed>|null, keyshops: array<string, mixed>|null}  $summary
     * @return array<string, mixed>|null
     */
    public function cheapest(array $summary): ?array
    {
        $best = $summary['best'];
        $keyshops = $summary['keyshops'] === null ? null : [...$summary['keyshops'], 'shopKey' => CurrentPrice::KEYSHOPS_KEY];

        return match (true) {
            $best === null => $keyshops,
            $keyshops === null => $best,
            default => $keyshops['effectiveCents'] < $best['effectiveCents'] ? $keyshops : $best,
        };
    }

    /**
     * Cena, podle které přehled řadí a filtruje (R30): oficiální nabídka,
     * nejlevnější včetně klíčů, nebo jen obchody s klíči. Nabídka s klíči má
     * příznak `keyshops` (karta ji pak ukáže jako hlavní cenu).
     *
     * @param  array{best: array<string, mixed>|null, playstation: array<string, mixed>|null, keyshops: array<string, mixed>|null}  $summary
     * @return array<string, mixed>|null
     */
    public function ranked(array $summary, PriceBasis $basis): ?array
    {
        $offer = match ($basis) {
            PriceBasis::Official => $summary['best'],
            PriceBasis::Cheapest => $this->cheapest($summary),
            PriceBasis::Keyshops => $summary['keyshops'] === null ? null : [...$summary['keyshops'], 'shopKey' => CurrentPrice::KEYSHOPS_KEY],
        };

        return $offer === null ? null : [...$offer, 'keyshops' => ($offer['shopKey'] ?? null) === CurrentPrice::KEYSHOPS_KEY];
    }

    /**
     * Všechny ceny pro detail hry.
     *
     * @return array<string, mixed>
     */
    public function detail(Game $game, string $currency): array
    {
        $latest = $game->currentPrices->sortByDesc('fetched_at')->first();

        return [
            'offers' => $this->offers($game, $currency, new PriceFilter),
            'keyshops' => $this->keyshops($game, $currency),
            'lows' => [
                'official' => $this->low($game, PriceScope::Official, $currency),
                'keyshops' => $this->low($game, PriceScope::Keyshops, $currency),
            ],
            'fetchedAt' => $latest instanceof CurrentPrice ? $latest->fetched_at->toIso8601String() : null,
        ];
    }

    /**
     * Nabídky oficiálních obchodů a PlayStation Store od nejlevnější podle filtru.
     * S volbou PS Plus se u PS Store počítá cena pro předplatitele.
     *
     * @return list<array<string, mixed>>
     */
    private function offers(Game $game, string $currency, PriceFilter $filter): array
    {
        return array_values($game->currentPrices
            ->filter(fn (CurrentPrice $price): bool => $price->source !== PriceSource::GgDeals && $filter->accepts($price->platform))
            ->map(function (CurrentPrice $price) use ($currency, $filter): array {
                $converted = $this->converted($price->price(), $currency);
                $member = ($memberPrice = $price->memberPrice()) === null ? null : $this->converted($memberPrice, $currency);

                return [
                    'shopKey' => $price->shop_key,
                    'shop' => $price->shop_name,
                    'edition' => $price->edition,
                    'platform' => $price->platform->value,
                    'price' => $converted['price'],
                    'original' => $converted['original'],
                    'regular' => ($regular = $price->regularPrice()) === null ? null : $this->converted($regular, $currency)['price'],
                    'cut' => $price->cut,
                    'member' => $member === null ? null : ['price' => $member['price'], 'original' => $member['original']],
                    'memberIncluded' => $price->member_included,
                    // Úroveň katalogu PS Plus a měsíční hra Essential (R35)
                    'memberTier' => $price->member_tier?->value,
                    'memberFree' => $price->member_free,
                    // Odchod z katalogu PS Plus (R49)
                    'memberLeavesAt' => $price->member_leaves_at?->toIso8601String(),
                    'drm' => $price->drm,
                    'url' => $price->url,
                    'expiresAt' => $price->expires_at?->toIso8601String(),
                    // Cena, podle které se řadí a filtruje (v měně zobrazení)
                    'effectiveCents' => $filter->withPlus && $member !== null ? $member['cents'] : $converted['cents'],
                ];
            })
            ->sortBy([['effectiveCents', 'asc'], ['shop', 'asc']])
            ->all());
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
            'effectiveCents' => $converted['cents'],
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
     * @return array{price: array{amount: float, currency: string}, original: array{amount: float, currency: string}|null, cents: int}
     */
    private function converted(Money $money, string $currency): array
    {
        $converted = $this->rates->convert($money, $currency);
        if ($converted === null || $converted->currency === $money->currency) {
            return ['price' => $money->toArray(), 'original' => null, 'cents' => ($converted ?? $money)->cents];
        }

        return ['price' => $converted->toArray(), 'original' => $money->toArray(), 'cents' => $converted->cents];
    }
}
