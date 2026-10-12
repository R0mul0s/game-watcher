<?php

/**
 * Data her pro Inertia stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Itad\ItadGame;
use App\Domain\Pricing\PriceBoard;
use App\Models\Game;

/**
 * Jedno místo, kde se z hry stává tvar pro Vue — komponenty nic nepočítají
 * (docs/CODING_GUIDELINES.md, sekce 5). Datum vydání jde jako Y-m-d, formát
 * dělá prohlížeč (Intl). Ceny chystá PriceBoard v měně zobrazení účtu (R14) —
 * hra musí mít načtené vazby currentPrices a priceLows.
 */
final class GamePresenter
{
    private const DATE_FORMAT = 'Y-m-d';

    /** Hodnocení od této hodnoty (0–100) je kladné — mez „Mostly Positive“ na Steamu. */
    private const REVIEW_GOOD_FROM = 70;

    /** Hodnocení od této hodnoty je smíšené, pod ní záporné (Steam „Mixed“). */
    private const REVIEW_MIXED_FROM = 40;

    public function __construct(private readonly PriceBoard $priceBoard) {}

    /**
     * Karta hry na přehledu sledovaných — s nejlevnější cenou.
     *
     * @return array<string, mixed>
     */
    public function card(Game $game, string $currency): array
    {
        return [
            ...$this->basics($game),
            'prices' => $this->priceBoard->summary($game, $currency),
        ];
    }

    /**
     * Detail hry s cenami a akcí sledovat / přestat sledovat.
     *
     * @return array<string, mixed>
     */
    public function detail(Game $game, bool $watching, string $currency): array
    {
        return [
            ...$this->basics($game),
            'prices' => $this->priceBoard->detail($game, $currency),
            'itadId' => $game->itad_id,
            'boxartUrl' => $game->boxart_url,
            'isMature' => $game->is_mature,
            'steamAppId' => $game->steam_app_id,
            'developers' => $game->developers ?? [],
            'publishers' => $game->publishers ?? [],
            'tags' => $game->tags ?? [],
            'reviews' => array_map(fn (array $review): array => [
                ...$review,
                'level' => $this->reviewLevel($review['score']),
            ], $game->reviews ?? []),
            'steamUrl' => $game->steamUrl(),
            'itadUrl' => $game->itad_url,
            'watching' => $watching,
            'unwatchUrl' => route('watchlist.destroy', $game, absolute: false),
        ];
    }

    /**
     * Výsledek hledání — odkaz na detail jen u hry, kterou už katalog má.
     *
     * @return array{itadId: string, title: string, type: string, thumbnailUrl: ?string, isMature: bool, watching: bool, detailUrl: ?string}
     */
    public function searchResult(ItadGame $game, ?Game $inCatalog, bool $watching): array
    {
        return [
            'itadId' => $game->id,
            'title' => $game->title,
            'type' => $game->type,
            'thumbnailUrl' => $game->thumbnailUrl,
            'isMature' => $game->mature,
            'watching' => $watching,
            'detailUrl' => $inCatalog === null ? null : route('games.show', $inCatalog, absolute: false),
        ];
    }

    /**
     * Údaje hry společné pro kartu i detail.
     *
     * @return array{slug: string, title: string, type: string, bannerUrl: ?string, releaseDate: ?string, url: string}
     */
    private function basics(Game $game): array
    {
        return [
            'slug' => $game->slug,
            'title' => $game->title,
            'type' => $game->type,
            'bannerUrl' => $game->banner_url ?? $game->boxart_url,
            'releaseDate' => $game->release_date?->format(self::DATE_FORMAT),
            'url' => route('games.show', $game, absolute: false),
        ];
    }

    /**
     * Úroveň hodnocení pro barvu skóre: good / mixed / bad.
     */
    private function reviewLevel(int $score): string
    {
        return match (true) {
            $score >= self::REVIEW_GOOD_FROM => 'good',
            $score >= self::REVIEW_MIXED_FROM => 'mixed',
            default => 'bad',
        };
    }
}
