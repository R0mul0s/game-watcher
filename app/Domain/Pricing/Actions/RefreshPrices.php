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
use App\Domain\Pricing\PriceRefreshResult;
use App\Domain\Pricing\StorePrices;
use App\Enums\PriceSource;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Oficiální obchody z ITAD, obchody s klíči z gg.deals (R9) — po dávkách,
 * jeden požadavek na dávku. Zdroje na sobě nezávisí: výpadek jednoho
 * (zalogoval ho klient) nezastaví druhý, jen se vrátí ve výsledku.
 */
final class RefreshPrices
{
    public function __construct(
        private readonly ItadClient $itad,
        private readonly GgDealsClient $ggDeals,
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

        return new PriceRefreshResult($updated, $failed);
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
