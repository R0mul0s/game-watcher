<?php

/**
 * Denní kontrola parserů zdrojů na referenčních hrách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Monitoring;

use App\Domain\Hltb\HltbClient;
use App\Domain\Metacritic\MetacriticClient;
use App\Domain\PlayStation\PlayStationClient;
use App\Domain\Steam\SteamClient;
use App\Enums\MonitoredSource;
use App\Models\SourceStatus;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Změna formátu stránky se nemusí projevit výpadkem (R48): PS Store bez
 * tlačítek nákupu vrátí produkt „nedá se koupit“, Steam bez seznamu jazyků
 * neznámou češtinu — data tiše chybí. Kanárek jednou denně stáhne referenční
 * hry (games.monitoring.canary), které ty údaje mají vždy, a chybějící údaj
 * hlásí jako výpadek zdroje „Kontrola parserů“ (stav zdrojů, /health/zdroje).
 */
final class ParserCanary
{
    /** Oddělovač zjištění ve zprávě chyby. */
    private const FAILURE_SEPARATOR = '; ';

    public function __construct(
        private readonly PlayStationClient $playStation,
        private readonly SteamClient $steam,
        private readonly HltbClient $hltb,
        private readonly MetacriticClient $metacritic,
    ) {}

    /**
     * Je čas na kontrolu? Jednou za games.monitoring.canary_interval_minutes.
     */
    public function isDue(): bool
    {
        $lastRun = SourceStatus::query()->where('source', MonitoredSource::Canary->value)->value('last_run_at');

        return $lastRun === null
            || CarbonImmutable::parse($lastRun)->lessThanOrEqualTo(CarbonImmutable::now()->subMinutes((int) config('games.monitoring.canary_interval_minutes')));
    }

    /**
     * Zkontroluje všechny zdroje; vrátí počet zdrojů a zjištění (prázdné = v pořádku).
     *
     * @return array{0: int, 1: list<string>}
     */
    public function handle(): array
    {
        $checks = [
            'playstation' => $this->checkPlayStation(...),
            'steam' => $this->checkSteam(...),
            'hltb' => $this->checkHltb(...),
            'metacritic' => $this->checkMetacritic(...),
        ];

        $failures = [];
        foreach ($checks as $source => $check) {
            $problem = $this->run($check);
            if ($problem !== null) {
                $failures[] = (string) __('app.monitoring.canary.'.$source).': '.$problem;
            }
        }

        return [count($checks), $failures];
    }

    /**
     * Zjištění jako jedna zpráva pro stav zdrojů.
     *
     * @param  list<string>  $failures
     */
    public static function message(array $failures): string
    {
        return implode(self::FAILURE_SEPARATOR, $failures);
    }

    /**
     * Provede kontrolu; výpadek zdroje je zjištění se zprávou zdroje (bez tajemství, R29).
     *
     * @param  Closure(): ?string  $check
     */
    private function run(Closure $check): ?string
    {
        try {
            return $check();
        } catch (SourceUnavailable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Until Dawn: produkt s běžnou cenou a jazyky (R20, R47).
     */
    private function checkPlayStation(): ?string
    {
        $product = $this->playStation->product((string) config('games.monitoring.canary.playstation_product'));

        return match (true) {
            $product === null => (string) __('app.monitoring.canary.missing'),
            $product->price === null => (string) __('app.monitoring.canary.playstation_price'),
            $product->czech === null => (string) __('app.monitoring.canary.languages'),
            default => null,
        };
    }

    /**
     * It Takes Two: žánry, režimy a jazyky (R25, R47).
     */
    private function checkSteam(): ?string
    {
        $details = $this->steam->appDetails((int) config('games.monitoring.canary.steam_app_id'));

        return match (true) {
            $details === null => (string) __('app.monitoring.canary.missing'),
            $details->genres === [] || $details->modes === [] => (string) __('app.monitoring.canary.steam_categories'),
            $details->czech === null => (string) __('app.monitoring.canary.languages'),
            default => null,
        };
    }

    /**
     * Alan Wake 2: délka hlavního příběhu (R44).
     */
    private function checkHltb(): ?string
    {
        $game = $this->hltb->game((int) config('games.monitoring.canary.hltb_game_id'));

        return match (true) {
            $game === null => (string) __('app.monitoring.canary.missing'),
            $game->mainMinutes === null => (string) __('app.monitoring.canary.hltb_playtime'),
            default => null,
        };
    }

    /**
     * Alan Wake 2: Metascore (R33).
     */
    private function checkMetacritic(): ?string
    {
        $game = $this->metacritic->game((string) config('games.monitoring.canary.metacritic_slug'));

        return match (true) {
            $game === null => (string) __('app.monitoring.canary.missing'),
            $game->score === null => (string) __('app.monitoring.canary.metacritic_score'),
            default => null,
        };
    }
}
