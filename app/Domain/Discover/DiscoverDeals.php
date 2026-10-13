<?php

/**
 * Stránka Objevit — slevy z ITAD podle štítků sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Discover;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Itad\ItadDealListing;
use App\Domain\Pricing\Money;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Jeden požadavek `deals/v2` na kombinaci filtrů, v cache discover.cache_seconds
 * (limit ITAD, R15). Výsledek je společný — hry, které uživatel sleduje,
 * se vynechají až potom. Nabídka štítků: předvolené (couch co-op…) a nejčastější
 * štítky sledovaných her (ze Steamu přes ITAD, R16). Jen obchody pro PC —
 * PlayStation Store žádný seznam slev nenabízí (R43).
 */
final class DiscoverDeals
{
    private const CACHE_PREFIX = 'itad:deals:';

    /** Značky ITAD → klíč textu na stránce. */
    private const FLAGS = ['N' => 'new_low', 'H' => 'low', 'S' => 'shop_low'];

    public function __construct(
        private readonly ItadClient $itad,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Slevy pro stránku, nabídka štítků a příznak výpadku ITAD.
     *
     * @return array{deals: list<array<string, mixed>>, tags: list<array{name: string, count: int|null}>, unavailable: bool}
     */
    public function build(User $user, DiscoverFilters $filters, string $currency): array
    {
        $watched = $user->watchedGames()->get(['games.itad_id', 'games.tags']);
        $tags = $this->tagOptions($watched->map(fn (Game $game): ?array => $game->tags)->values()->all(), $filters->tags);

        $listings = $this->listings($filters);
        if ($listings === null) {
            return ['deals' => [], 'tags' => $tags, 'unavailable' => true];
        }

        $watchedIds = $watched->pluck('itad_id')->all();
        $listings = array_values(array_filter($listings, fn (ItadDealListing $listing): bool => ! in_array($listing->itadId, $watchedIds, true)));
        // Hry, které katalog už má (sleduje je někdo jiný) — odkaz na detail
        $slugs = Game::query()->whereIn('itad_id', array_map(fn (ItadDealListing $listing): string => $listing->itadId, $listings))->pluck('slug', 'itad_id');

        return [
            'deals' => array_map(fn (ItadDealListing $listing): array => $this->present($listing, $currency, $slugs->get($listing->itadId)), $listings),
            'tags' => $tags,
            'unavailable' => false,
        ];
    }

    /**
     * Slevy z ITAD přes cache; null = ITAD teď neodpovídá.
     *
     * @return list<ItadDealListing>|null
     */
    private function listings(DiscoverFilters $filters): ?array
    {
        $key = self::CACHE_PREFIX.$filters->cacheKey();

        /** @var list<array{itadId: string, title: string, type: string, bannerUrl: string|null, shopName: string, cents: int, regularCents: int|null, currency: string, cut: int, flag: string|null, url: string|null, expiresAt: string|null}>|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_map(ItadDealListing::fromArray(...), $cached);
        }

        try {
            $listings = $this->itad->deals($filters->itadFilter(), $filters->sort->itadSort(), (int) config('games.discover.limit'));
        } catch (ItadUnavailable) {
            return null;
        }

        Cache::put($key, array_map(fn (ItadDealListing $listing): array => $listing->toArray(), $listings), (int) config('games.discover.cache_seconds'));

        return $listings;
    }

    /**
     * Nabídka štítků: předvolené, nejčastější štítky sledovaných her a vybrané
     * (i když v nabídce nejsou — výběr z adresy jde zrušit).
     *
     * @param  array<int, list<string>|null>  $gameTags
     * @param  list<string>  $selected
     * @return list<array{name: string, count: int|null}>
     */
    private function tagOptions(array $gameTags, array $selected): array
    {
        $counts = [];
        foreach ($gameTags as $tags) {
            foreach ($tags ?? [] as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        arsort($counts);
        /** @var list<string> $presets */
        $presets = config('games.discover.preset_tags');
        $options = [];
        foreach ([...$presets, ...array_keys(array_slice($counts, 0, (int) config('games.discover.tag_suggestions'), true)), ...$selected] as $tag) {
            $options[$tag] ??= ['name' => (string) $tag, 'count' => $counts[$tag] ?? null];
        }

        return array_values($options);
    }

    /**
     * Sleva pro stránku v měně zobrazení (R14).
     *
     * @return array<string, mixed>
     */
    private function present(ItadDealListing $listing, string $currency, ?string $slug): array
    {
        $converted = $this->rates->convert($listing->price, $currency);

        return [
            'itadId' => $listing->itadId,
            'title' => $listing->title,
            'bannerUrl' => $listing->bannerUrl,
            'shop' => $listing->shopName,
            'price' => ($converted ?? $listing->price)->toArray(),
            // Cena v měně obchodu, když se liší (jako ve výpisu obchodů)
            'original' => $converted === null || $converted->currency === $listing->price->currency ? null : $listing->price->toArray(),
            'regular' => $listing->regular === null ? null : $this->display($listing->regular, $currency),
            'cut' => $listing->cut,
            'flag' => self::FLAGS[$listing->flag] ?? null,
            'url' => $listing->url,
            'expiresAt' => $listing->expiresAt?->toIso8601String(),
            'detailUrl' => $slug === null ? null : route('games.show', $slug, absolute: false),
        ];
    }

    /**
     * Částka v měně zobrazení; bez kurzu v měně obchodu.
     *
     * @return array{amount: float, currency: string}
     */
    private function display(Money $money, string $currency): array
    {
        return ($this->rates->convert($money, $currency) ?? $money)->toArray();
    }
}
