<?php

/**
 * Převzetí hry z IsThereAnyDeal do katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadClient;
use App\Domain\Itad\ItadGameInfo;
use App\Models\Game;
use Carbon\CarbonImmutable;

/**
 * Stáhne model hry (`games/info`) a uloží ho do katalogu — novou hru založí,
 * existující podle ITAD ID aktualizuje (R10).
 */
final class ImportGame
{
    /** Údaje, které při obnově prázdná hodnota z ITAD nepřepíše (doplnil je PS Store, R22). */
    private const KEPT_WHEN_EMPTY = ['boxart_url', 'banner_url', 'release_date', 'publishers'];

    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Uložená hra; null, když ITAD hru s tímto ID nezná.
     *
     * @throws ItadUnavailable
     */
    public function handle(string $itadId): ?Game
    {
        $info = $this->itad->info($itadId);
        if ($info === null) {
            return null;
        }

        return Game::query()->updateOrCreate(
            ['itad_id' => $itadId],
            ['slug' => $info->game->slug, ...$this->attributes($info)],
        );
    }

    /**
     * Obnoví snímek údajů hry z ITAD (R34) — datum vydání, hodnocení, štítky,
     * obrázky. Údaje, které ITAD nemá a doplnil je PS Store (obal, banner, datum
     * vydání, vydavatel u exkluzivit, R22), se prázdnou hodnotou nepřepíšou.
     * Slug zůstává — je v adrese detailu. Vrátí false, když ITAD hru už nezná
     * (snímek zůstane, jen se označí jako zkontrolovaný).
     *
     * @throws ItadUnavailable
     */
    public function refresh(Game $game): bool
    {
        $info = $this->itad->info($game->itad_id);
        if ($info === null) {
            $game->update(['info_refreshed_at' => CarbonImmutable::now()]);

            return false;
        }

        $attributes = $this->attributes($info);
        foreach (self::KEPT_WHEN_EMPTY as $column) {
            if ($attributes[$column] === null || $attributes[$column] === []) {
                unset($attributes[$column]);
            }
        }

        $game->update($attributes);

        return true;
    }

    /**
     * Sloupce hry z modelu ITAD (bez slugu).
     *
     * @return array<string, mixed>
     */
    private function attributes(ItadGameInfo $info): array
    {
        return [
            'title' => $info->game->title,
            'type' => $info->game->type,
            'is_mature' => $info->game->mature,
            'steam_app_id' => $info->steamAppId,
            'release_date' => $info->releaseDate,
            'developers' => $info->developers,
            'publishers' => $info->publishers,
            'tags' => $info->tags,
            'reviews' => $info->reviews,
            'boxart_url' => $info->game->boxartUrl,
            'banner_url' => $info->game->bannerUrl,
            'itad_url' => $info->url,
            'info_refreshed_at' => CarbonImmutable::now(),
        ];
    }
}
