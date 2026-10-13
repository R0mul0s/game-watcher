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
 * pro celý klíč (R15, R34).
 */
final class RefreshGameInfo
{
    public function __construct(private readonly ImportGame $importGame) {}

    /**
     * Sledované hry s nejstarším snímkem údajů, starším než mez.
     *
     * @return Collection<int, Game>
     */
    public function pending(): Collection
    {
        $staleBefore = CarbonImmutable::now()->subDays((int) config('games.catalog.info_refresh_days'));

        return Game::query()
            ->whereHas('watchlistItems')
            ->where('info_refreshed_at', '<', $staleBefore)
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
