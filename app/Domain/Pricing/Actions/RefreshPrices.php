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
use App\Enums\PriceSource;
use App\Models\Game;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * Oficiální obchody z ITAD, obchody s klíči z gg.deals (R9) — po dávkách,
 * jeden požadavek na dávku; PlayStation Store po jednotlivých produktech (R20).
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

        try {
            $updated[PriceSource::Itad->value] = $this->refreshItad($games, $fetchedAt);
        } catch (ItadUnavailable) {
            $failed[] = PriceSource::Itad;
        }

        try {
            $updated[PriceSource::GgDeals->value] = $this->refreshGgDeals($games, $fetchedAt);
        } catch (GgDealsUnavailable) {
            $failed[] = PriceSource::GgDeals;
        }

        [$updated[PriceSource::PlayStation->value], $playStationFailed] = $this->refreshPlayStation($games, $fetchedAt);
        if ($playStationFailed) {
            $failed[] = PriceSource::PlayStation;
        }

        return new PriceRefreshResult($updated, $failed);
    }

    /**
     * Ceny produktů PlayStation Store propojených s hrami (R20) — po jedné
     * stránce s pauzou. Výpadek jednoho produktu ostatní nezastaví; vrátí počet
     * aktualizovaných produktů a jestli některý selhal.
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: bool}
     */
    private function refreshPlayStation(Collection $games, CarbonImmutable $fetchedAt): array
    {
        $listings = StoreListing::query()
            ->whereIn('game_id', $games->pluck('id'))
            ->where('source', PriceSource::PlayStation->value)
            ->orderBy('id')
            ->get();

        $count = 0;
        $failed = false;
        foreach ($listings as $index => $listing) {
            if ($index > 0) {
                Sleep::usleep((int) config('games.sources.playstation.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            try {
                $product = $this->playStation->product($listing->external_id);
            } catch (PlayStationUnavailable) {
                $failed = true;

                continue;
            }

            if ($product === null) {
                // Produkt z obchodu zmizel — cena pryč, propojení zůstává (může se vrátit)
                $this->storePrices->forgetListing($listing);

                continue;
            }

            $this->storePrices->storePlayStation($listing, $product, $fetchedAt);
            // Počet hráčů pro filtr přehledu (R25) — obchod ho může doplnit později
            $listing->update(['local_players' => $product->localPlayers, 'online_players' => $product->onlinePlayers]);
            $count++;
        }

        return [$count, $failed];
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
