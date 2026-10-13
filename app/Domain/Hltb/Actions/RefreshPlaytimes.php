<?php

/**
 * Délka hraní sledovaných her z HowLongToBeat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb\Actions;

use App\Domain\Hltb\Exceptions\HltbUnavailable;
use App\Domain\Hltb\HltbClient;
use App\Domain\Hltb\HltbGame;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Hra známá z odkazu nebo dřívějšího hledání se obnoví ze své stránky, jinak
 * se hledá podle názvu (R44). Výsledek hledání se vezme, jen když název po
 * odstranění všeho kromě písmen a číslic sedí přesně (napřed celý, pak bez přípony edice
 * „– GOTY Edition“) a je jediný — víc stejných rozhodne rok vydání. Jinak
 * zbývá odkaz od uživatele. Volá ho přidání hry a cron po malých dávkách
 * s pauzou; hra se obnovuje po games.sources.hltb.refresh_days (délky se
 * mění s počtem hráčů, nevydané hry je dostanou později). DLC se nehledají.
 */
final class RefreshPlaytimes
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;

    /** Přípona edice v názvu z ITAD, kterou HLTB u hry nemá. */
    private const EDITION_SUFFIX_PATTERN = '/[\s:–-]+(?:(?:game of the year|goty|complete|definitive|deluxe|digital deluxe|ultimate|gold|standard|enhanced)\s+)?edition$/iu';

    /** Značky v názvech z ITAD, které HLTB nemá. */
    private const TRADEMARKS = ['™', '®', '©'];

    public function __construct(private readonly HltbClient $client) {}

    /**
     * Sledované hry, které se ještě nezkoušely nebo zkoušely před víc než
     * refresh_days — nejvýš per_run, nejdřív nezkoušené.
     *
     * @return Collection<int, Game>
     */
    public function pending(): Collection
    {
        $staleBefore = CarbonImmutable::now()->subDays((int) config('games.sources.hltb.refresh_days'));

        return Game::query()
            ->whereHas('watchlistItems')
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', Game::TYPE_DLC))
            ->where(fn ($query) => $query->whereNull('hltb_checked_at')->orWhere('hltb_checked_at', '<', $staleBefore))
            ->orderByRaw('hltb_checked_at IS NOT NULL')
            ->orderBy('hltb_checked_at')
            ->orderBy('id')
            ->limit((int) config('games.sources.hltb.per_run'))
            ->get();
    }

    /**
     * Stáhne a uloží délky her; vrátí počet zkontrolovaných her a zprávu
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
                Sleep::usleep((int) config('games.sources.hltb.request_delay_ms') * self::MICROSECONDS_PER_MILLISECOND);
            }

            try {
                $this->refresh($game);
            } catch (HltbUnavailable $e) {
                return [$count, $e->getMessage()];
            }

            $count++;
        }

        return [$count, null];
    }

    /**
     * Uloží výsledek; nenalezená hra si ID nepamatuje (příště se zkusí znovu).
     */
    public function store(Game $game, ?HltbGame $hltb): void
    {
        $game->update([
            'hltb_id' => $hltb?->id,
            'hltb_main_minutes' => $hltb?->mainMinutes,
            'hltb_extra_minutes' => $hltb?->extraMinutes,
            'hltb_complete_minutes' => $hltb?->completeMinutes,
            'hltb_checked_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Výsledek hledání, který je určitě tahle hra; null = žádný nebo nejasné.
     *
     * @param  list<HltbGame>  $results
     */
    public static function match(Game $game, array $results): ?HltbGame
    {
        // Nejdřív celý název (HLTB má i „… – Complete Edition“), pak bez přípony edice
        $named = fn (string $title): array => array_values(array_filter(
            $results,
            fn (HltbGame $result): bool => self::normalized($result->name) === self::normalized($title),
        ));
        $candidates = $named($game->title) ?: $named((string) preg_replace(self::EDITION_SUFFIX_PATTERN, '', $game->title));

        $year = $game->release_date?->year;
        if (count($candidates) > 1 && $year !== null) {
            $candidates = array_values(array_filter($candidates, fn (HltbGame $result): bool => $result->releaseYear === $year));
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Délky jedné hry — ze známé stránky, nebo z hledání podle názvu.
     *
     * @throws HltbUnavailable
     */
    private function refresh(Game $game): void
    {
        $hltb = $game->hltb_id !== null
            ? $this->client->game($game->hltb_id)
            : self::match($game, $this->client->search(self::searchTitle($game->title)));

        $this->store($game, $hltb);
    }

    /**
     * Název pro hledání — bez značek ™ ® © a přípony edice; HLTB hledá podle
     * slov a „Dawn™“ ani „Edition“ by nenašel.
     */
    private static function searchTitle(string $title): string
    {
        return trim((string) preg_replace(self::EDITION_SUFFIX_PATTERN, '', str_replace(self::TRADEMARKS, '', $title)));
    }

    /**
     * Název pro porovnání — jen malá písmena a číslice bez diakritiky
     * („CONTROL Resonant“ = „Control: Resonant“).
     */
    private static function normalized(string $title): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($title)));
    }
}
