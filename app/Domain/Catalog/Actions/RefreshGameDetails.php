<?php

/**
 * Žánry, režimy a čeština her ze Steamu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Steam\Exceptions\SteamUnavailable;
use App\Domain\Steam\SteamClient;
use App\Enums\GameMode;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * Filtr přehledu podle žánru, počtu hráčů (R25) a češtiny (R47) potřebuje údaje, které ITAD
 * nemá — ze Steamu jen pro hry se Steam App ID. Cron doplňuje sledované hry
 * bez údajů nebo se starými údaji po malých dávkách (limit Steamu), nově
 * přidaná hra je dostane hned. Hra, kterou Steam nezná, se označí jako
 * stažená bez údajů, aby se neptala znovu při každém běhu.
 */
final class RefreshGameDetails
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;

    public function __construct(private readonly SteamClient $steam) {}

    /**
     * Sledované hry se Steamem, kterým údaje chybí nebo zastaraly — nejvýš
     * games.sources.steam.details_per_run, nejdřív ty bez údajů.
     *
     * @return Collection<int, Game>
     */
    public function pending(): Collection
    {
        $staleBefore = CarbonImmutable::now()->subDays((int) config('games.sources.steam.details_refresh_days'));

        return Game::query()
            ->whereNotNull('steam_app_id')
            ->whereHas('watchlistItems')
            ->where(fn ($query) => $query->whereNull('steam_details_at')->orWhere('steam_details_at', '<', $staleBefore))
            ->orderByRaw('steam_details_at IS NOT NULL')
            ->orderBy('steam_details_at')
            ->orderBy('id')
            ->limit((int) config('games.sources.steam.details_per_run'))
            ->get();
    }

    /**
     * Stáhne a uloží údaje her; vrátí počet uložených. Výpadek Steamu dávku
     * ukončí (limit by se jen prohluboval) — zbytek dožene další běh.
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: bool} počet uložených her a jestli Steam selhal
     */
    public function handle(Collection $games): array
    {
        $count = 0;
        foreach ($games->filter(fn (Game $game): bool => $game->steam_app_id !== null)->values() as $index => $game) {
            if ($index > 0) {
                Sleep::usleep((int) config('games.sources.steam.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            try {
                $details = $this->steam->appDetails((int) $game->steam_app_id);
            } catch (SteamUnavailable) {
                return [$count, true];
            }

            $game->update([
                'genres' => $details?->genres,
                'modes' => $details === null ? null : array_map(fn (GameMode $mode): string => $mode->value, $details->modes),
                'czech' => $details?->czech,
                'steam_details_at' => CarbonImmutable::now(),
            ]);
            $count++;
        }

        return [$count, false];
    }
}
