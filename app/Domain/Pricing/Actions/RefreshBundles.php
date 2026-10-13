<?php

/**
 * Aktivní bundly se sledovanými hrami z IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Domain\Itad\ItadBundle;
use App\Domain\Itad\ItadClient;
use App\Models\Game;
use App\Models\GameBundle;
use Illuminate\Support\Facades\DB;

/**
 * Krok cronu (R51): seznam všech aktivních bundlů (bundles/v1, jeden požadavek
 * na 50 bundlů) se porovná se sledovanými hrami podle ITAD ID — nikdy dotaz po hrách.
 * Tabulka game_bundles se celá přepíše; výpadek ITAD ji nechá z minula.
 */
final class RefreshBundles
{
    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Přepíše bundly sledovaných her; vrátí počet her v nějakém bundlu.
     *
     * @throws ItadUnavailable
     */
    public function handle(): int
    {
        // Jako ceny jen sledované hry — bez nich se ITAD vůbec neptá
        $watched = Game::query()->whereHas('watchlistItems');
        $bundles = $watched->exists() ? $this->itad->bundles() : [];

        $itadIds = array_values(array_unique(array_merge(...array_map(fn (ItadBundle $bundle): array => array_keys($bundle->games), $bundles))));
        $gameIds = $watched->whereIn('itad_id', $itadIds)->pluck('id', 'itad_id');

        $rows = [];
        foreach ($bundles as $bundle) {
            foreach ($bundle->games as $itadId => $price) {
                $gameId = $gameIds->get($itadId);
                if ($gameId === null) {
                    continue;
                }

                $rows[] = [
                    'game_id' => $gameId,
                    'bundle_id' => $bundle->id,
                    'title' => $bundle->title,
                    'shop' => $bundle->shop,
                    'url' => $bundle->url,
                    'amount' => $price?->toDecimal(),
                    'currency' => $price?->currency,
                    'expires_at' => $bundle->expiresAt,
                ];
            }
        }

        DB::transaction(function () use ($rows): void {
            GameBundle::query()->delete();
            if ($rows !== []) {
                GameBundle::query()->insert($rows);
            }
        });

        return count(array_unique(array_column($rows, 'game_id')));
    }
}
