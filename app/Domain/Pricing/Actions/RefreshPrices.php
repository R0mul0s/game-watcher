<?php

/**
 * Stažení aktuálních cen her ze všech zdrojů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\GgDeals\Exceptions\GgDealsUnavailable;
use App\Domain\GgDeals\GgDealsClient;
use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Domain\PlayStation\PlayStationClient;
use App\Domain\Pricing\PriceRefreshResult;
use App\Domain\Pricing\StorePrices;
use App\Domain\Retail\Exceptions\RetailFormatChanged;
use App\Domain\Retail\Exceptions\RetailUnavailable;
use App\Domain\Retail\RetailClient;
use App\Domain\Retail\RetailUrl;
use App\Enums\PriceSource;
use App\Models\Game;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * Oficiální obchody z ITAD, obchody s klíči z gg.deals (R9) — po dávkách,
 * jeden požadavek na dávku; PlayStation Store a krabicové hry z Xzone
 * a Heureky (R52) po jednotlivých produktech (R20).
 * Zdroje na sobě nezávisí: výpadek jednoho (zalogoval ho klient) nezastaví
 * ostatní, jen se vrátí ve výsledku.
 */
final class RefreshPrices
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;

    public function __construct(
        private readonly ItadClient $itad,
        private readonly GgDealsClient $ggDeals,
        private readonly PlayStationClient $playStation,
        private readonly RetailClient $retail,
        private readonly StorePrices $storePrices,
    ) {}

    /**
     * Ceny všech her, které někdo sleduje — krok cron URL. Hry, které nikdo
     * nesleduje, zůstávají v katalogu bez nových cen (šetří limity API).
     */
    public function watched(): PriceRefreshResult
    {
        return $this->handle(Game::query()->whereHas('watchlistItems')->orderBy('id')->get());
    }

    /**
     * Stáhne a uloží ceny her.
     *
     * @param  Collection<int, Game>  $games
     */
    public function handle(Collection $games): PriceRefreshResult
    {
        $fetchedAt = CarbonImmutable::now();
        $updated = [];
        $failed = [];
        $errors = [];
        $expected = [
            PriceSource::Itad->value => $games->count(),
            PriceSource::GgDeals->value => $games->whereNotNull('steam_app_id')->count(),
        ];

        try {
            $updated[PriceSource::Itad->value] = $this->refreshItad($games, $fetchedAt);
        } catch (ItadUnavailable $e) {
            $failed[] = PriceSource::Itad;
            $errors[PriceSource::Itad->value] = $e->getMessage();
        }

        try {
            $updated[PriceSource::GgDeals->value] = $this->refreshGgDeals($games, $fetchedAt);
        } catch (GgDealsUnavailable $e) {
            $failed[] = PriceSource::GgDeals;
            $errors[PriceSource::GgDeals->value] = $e->getMessage();
        }

        [$updated[PriceSource::PlayStation->value], $expected[PriceSource::PlayStation->value], $playStationError]
            = $this->refreshPlayStation($games, $fetchedAt);
        if ($playStationError !== null) {
            $failed[] = PriceSource::PlayStation;
            $errors[PriceSource::PlayStation->value] = $playStationError;
        }

        // Krabicové hry (R52) — po produktech jako PS Store
        foreach ([PriceSource::Xzone, PriceSource::Heureka] as $source) {
            [$updated[$source->value], $expected[$source->value], $retailError] = $this->refreshRetail($games, $source, $fetchedAt);
            if ($retailError !== null) {
                $failed[] = $source;
                $errors[$source->value] = $retailError;
            }
        }

        return new PriceRefreshResult($updated, $failed, $expected, $errors);
    }

    /**
     * Ceny produktů PlayStation Store propojených s hrami (R20) — po jedné
     * stránce s pauzou. Výpadek jednoho produktu ostatní nezastaví; vrátí počet
     * aktualizovaných produktů, počet propojených produktů a zprávu poslední
     * chyby (null, když žádný produkt neselhal).
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: int, 2: string|null}
     */
    private function refreshPlayStation(Collection $games, CarbonImmutable $fetchedAt): array
    {
        $listings = StoreListing::query()
            ->whereIn('game_id', $games->pluck('id'))
            ->where('source', PriceSource::PlayStation->value)
            ->orderBy('id')
            ->get();

        $count = 0;
        $error = null;
        foreach ($listings as $index => $listing) {
            if ($index > 0) {
                Sleep::usleep((int) config('games.sources.playstation.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            try {
                $product = $this->playStation->product($listing->external_id);
            } catch (PlayStationUnavailable $e) {
                $error = $e->getMessage();

                continue;
            }

            if ($product === null) {
                // Produkt z obchodu zmizel — cena pryč, propojení zůstává (může se vrátit)
                $this->storePrices->forgetListing($listing);

                continue;
            }

            $this->storePrices->storePlayStation($listing, $product, $fetchedAt);
            // Počet hráčů pro filtr přehledu (R25) a hodnocení hráčů (R33) — obchod je mění
            $listing->update($product->listingDetails());
            $count++;
        }

        return [$count, $listings->count(), $error];
    }

    /**
     * Ceny krabicových her jednoho obchodu (R52) — po jedné stránce s pauzou.
     * Stránka, která zmizela, cenu smaže; výpadek nebo změna formátu ji nechá.
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: int, 2: string|null} počet aktualizovaných, počet propojených, poslední chyba
     */
    private function refreshRetail(Collection $games, PriceSource $source, CarbonImmutable $fetchedAt): array
    {
        $listings = StoreListing::query()
            ->whereIn('game_id', $games->pluck('id'))
            ->where('source', $source->value)
            ->orderBy('id')
            ->get();

        $count = 0;
        $error = null;
        foreach ($listings as $index => $listing) {
            if ($index > 0) {
                Sleep::usleep((int) config('games.sources.retail.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            $url = RetailUrl::parse($listing->url);
            if ($url === null) {
                continue;
            }

            try {
                $product = $this->retail->product($url);
            } catch (RetailUnavailable|RetailFormatChanged $e) {
                $error = $e->getMessage();

                continue;
            }

            if ($product === null) {
                $this->storePrices->forgetListing($listing);

                continue;
            }

            $this->storePrices->storeRetail($listing, $product, $fetchedAt);
            $count++;
        }

        return [$count, $listings->count(), $error];
    }

    /**
     * Ceny oficiálních obchodů; vrátí počet aktualizovaných her.
     *
     * @param  Collection<int, Game>  $games
     *
     * @throws ItadUnavailable
     */
    private function refreshItad(Collection $games, CarbonImmutable $fetchedAt): int
    {
        $count = 0;
        foreach ($games->chunk((int) config('games.prices.itad_batch_size')) as $batch) {
            $prices = $this->itad->prices(array_values($batch->map(fn (Game $game): string => $game->itad_id)->all()));
            foreach ($batch as $game) {
                if (isset($prices[$game->itad_id])) {
                    $this->storePrices->storeItad($game, $prices[$game->itad_id], $fetchedAt);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Nejnižší ceny obchodů s klíči — jen hry se Steam App ID; vrátí počet aktualizovaných her.
     *
     * @param  Collection<int, Game>  $games
     *
     * @throws GgDealsUnavailable
     */
    private function refreshGgDeals(Collection $games, CarbonImmutable $fetchedAt): int
    {
        // Edice se stejným Steam App ID dostanou stejnou cenu — gg.deals je nerozliší
        $byAppId = $games->whereNotNull('steam_app_id')->groupBy('steam_app_id');
        $count = 0;

        foreach ($byAppId->keys()->chunk((int) config('games.prices.ggdeals_batch_size')) as $appIds) {
            $prices = $this->ggDeals->prices(array_values($appIds->map(fn (mixed $id): int => (int) $id)->all()));
            foreach ($prices as $appId => $gamePrices) {
                foreach ($byAppId->get($appId, collect()) as $game) {
                    $this->storePrices->storeGgDeals($game, $gamePrices, $fetchedAt);
                    $count++;
                }
            }
        }

        return $count;
    }
}
