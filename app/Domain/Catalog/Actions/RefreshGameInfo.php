<?php

/**
 * Obnova snímku údajů sledovaných her z ITAD.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Údaje hry se berou z ITAD při přidání (R16) a stárnou — nevydaná hra dostane
 * datum vydání a Metascore až později (R32). Krok cronu obnoví snímky starší
 * než games.catalog.info_refresh_days, nejvýš info_refresh_per_run her za běh:
 * každá hra je jeden požadavek `games/info` a limit ITAD je 100 za 5 minut
 * pro celý klíč (R15, R34). Nevydané hry a hry bez data vydání se obnovují
 * po info_refresh_days_upcoming dnech — datum vydání se posouvá (R41).
 */
final class RefreshGameInfo
{
    public function __construct(private readonly ImportGame $importGame) {}

    /**
     * Sledované hry s nejstarším snímkem údajů, starším než mez (u nevydaných
     * her kratší).
     *
     * @return Collection<int, Game>
     */
    public function pending(): Collection
    {
        $now = CarbonImmutable::now();
        $staleBefore = $now->subDays((int) config('games.catalog.info_refresh_days'));
        $upcomingStaleBefore = $now->subDays((int) config('games.catalog.info_refresh_days_upcoming'));

        return Game::query()
            ->whereHas('watchlistItems')
            ->where(fn ($query) => $query
                ->where('info_refreshed_at', '<', $staleBefore)
                ->orWhere(fn ($upcoming) => $upcoming
                    ->where('info_refreshed_at', '<', $upcomingStaleBefore)
                    ->where(fn ($date) => $date->whereNull('release_date')->orWhere('release_date', '>', Game::today()->toDateString()))))
            ->orderBy('info_refreshed_at')
            ->orderBy('id')
            ->limit((int) config('games.catalog.info_refresh_per_run'))
            ->get();
    }

    /**
     * Obnoví údaje her; vrátí počet zkontrolovaných a zprávu výpadku ITAD
     * (null = bez výpadku). Výpadek dávku ukončí — zbytek dožene další běh.
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: string|null}
     */
    public function handle(Collection $games): array
    {
        $count = 0;
        foreach ($games as $game) {
            try {
                $this->importGame->refresh($game);
            } catch (ItadUnavailable $e) {
                return [$count, $e->getMessage()];
            }

            $count++;
        }

        return [$count, null];
    }
}
