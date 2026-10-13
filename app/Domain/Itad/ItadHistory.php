<?php

/**
 * Historie cen hry z ITAD přes cache.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Itad;

use App\Domain\Itad\Exceptions\ItadUnavailable;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Jedna historie za chart.history_days dní pro graf ceny (R28) i „kup, nebo
 * počkej“ (R42) — detail hry ITAD volá nejvýš jednou za chart.cache_seconds,
 * nikdy v cyklu po hrách. V cache jen pole (ItadPriceChange::toArray()).
 */
final class ItadHistory
{
    /** Verze v klíči — starší záznamy v cache neměly slevu (cut, R42). */
    private const CACHE_PREFIX = 'itad:history:v2:';

    public function __construct(private readonly ItadClient $itad) {}

    /**
     * Historie od začátku období grafu; null = ITAD teď neodpovídá.
     *
     * @return list<ItadPriceChange>|null
     */
    public function forGame(Game $game): ?array
    {
        $key = self::CACHE_PREFIX.$game->itad_id;

        /** @var list<array{at: string, shopId: int, shopName: string, cents: int, currency: string, cut: int}>|null $cached */
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return array_map(ItadPriceChange::fromArray(...), $cached);
        }

        try {
            $changes = $this->itad->history($game->itad_id, self::start());
        } catch (ItadUnavailable) {
            return null;
        }

        Cache::put($key, array_map(fn (ItadPriceChange $change): array => $change->toArray(), $changes), (int) config('games.chart.cache_seconds'));

        return $changes;
    }

    /**
     * Začátek období historie — místní den před chart.history_days dny.
     */
    public static function start(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('games.display_timezone'))->startOfDay()->subDays((int) config('games.chart.history_days'));
    }
}
