<?php

/**
 * Metascore z Metacritic pro hry, kterým ho nedává ITAD.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic\Actions;

use App\Domain\Metacritic\Exceptions\MetacriticUnavailable;
use App\Domain\Metacritic\MetacriticClient;
use App\Domain\Metacritic\MetacriticGame;
use App\Domain\Metacritic\MetacriticUrl;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

/**
 * Exkluzivity PS5 a další hry bez Metascore v ITAD (R33). Stránka se najde
 * podle slugu z odkazu od uživatele, jinak odvozeného z názvu — odvozená stránka
 * platí, jen když její název dává stejný slug (jiná hra se stejnou adresou se
 * nevezme). Volá ho přidání hry a cron po malých dávkách s pauzou; hra se
 * zkouší znovu po games.sources.metacritic.refresh_days (nevydané hry
 * hodnocení dostanou později).
 */
final class RefreshMetascores
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;

    public function __construct(private readonly MetacriticClient $client) {}

    /**
     * Sledované hry bez Metascore z ITAD, které se ještě nezkoušely nebo
     * zkoušely před víc než refresh_days — nejvýš per_run, nejdřív nezkoušené.
     *
     * @return Collection<int, Game>
     */
    public function pending(): Collection
    {
        $staleBefore = CarbonImmutable::now()->subDays((int) config('games.sources.metacritic.refresh_days'));

        return Game::query()
            ->whereHas('watchlistItems')
            ->where(fn ($query) => $query->whereNull('metacritic_checked_at')->orWhere('metacritic_checked_at', '<', $staleBefore))
            ->orderByRaw('metacritic_checked_at IS NOT NULL')
            ->orderBy('metacritic_checked_at')
            ->orderBy('id')
            ->get()
            // Metascore z ITAD je ve snímku JSON — filtr v PHP (sledovaných her jsou desítky)
            ->filter(fn (Game $game): bool => $game->itadMetascore() === null)
            ->take((int) config('games.sources.metacritic.per_run'))
            ->values();
    }

    /**
     * Stáhne a uloží Metascore her; vrátí počet zkontrolovaných her a zprávu
     * výpadku (null = bez výpadku). Výpadek dávku ukončí — zbytek dožene další běh.
     *
     * @param  Collection<int, Game>  $games
     * @return array{0: int, 1: string|null}
     */
    public function handle(Collection $games): array
    {
        $count = 0;
        foreach ($games->values() as $index => $game) {
            if ($index > 0) {
                Sleep::usleep((int) config('games.sources.metacritic.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            try {
                $this->refresh($game);
            } catch (MetacriticUnavailable $e) {
                return [$count, $e->getMessage()];
            }

            $count++;
        }

        return [$count, null];
    }

    /**
     * Metascore jedné hry — ze známé stránky, nebo z odvozené podle názvu.
     *
     * @throws MetacriticUnavailable
     */
    private function refresh(Game $game): void
    {
        $slug = $game->metacritic_slug ?? MetacriticUrl::slugFromTitle($game->title);
        $page = $slug === null ? null : $this->client->game($slug);

        // Odvozená stránka musí patřit stejné hře — stránka z odkazu se nekontroluje
        if ($page !== null && $game->metacritic_slug === null && MetacriticUrl::slugFromTitle($page->name) !== $slug) {
            $page = null;
        }

        $this->store($game, $page === null ? null : $slug, $page);
    }

    /**
     * Uloží výsledek; nenalezená hra si slug nepamatuje (příště se zkusí znovu).
     */
    public function store(Game $game, ?string $slug, ?MetacriticGame $page): void
    {
        $game->update([
            'metacritic_slug' => $slug,
            'metacritic_score' => $page?->score,
            'metacritic_reviews' => $page?->score === null ? null : $page->reviews,
            'metacritic_checked_at' => CarbonImmutable::now(),
        ]);
    }
}
