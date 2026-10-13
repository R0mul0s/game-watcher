<?php

/**
 * „Kup, nebo počkej?“ — doporučení podle historie cen hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Domain\Currency\ExchangeRates;
use App\Domain\Itad\ItadHistory;
use App\Domain\Itad\ItadPriceChange;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;

/**
 * Z historie ITAD (obchody pro PC, stejná jako graf ceny — R28) spočítá
 * nejnižší cenu každého dne. Rozhoduje cena, ne sleva: mezi desítkami obchodů
 * má nějakou slevu skoro vždycky některý, „ve slevě“ by tak hra byla pořád.
 * Za poslední rok (před dneškem): v kolika procentech dní byla hra levněji
 * než teď, roční minimum a kdy byla naposledy u minima (do outlook.near_low_percent
 * % nad ním); z kalendáře výprodejů (games.sales_calendar) nejbližší výprodej
 * obchodu, ve kterém se hra prodává, a za kolik tam byla loni. Doporučení (R42):
 * - buy — teď na ročním minimu (s tolerancí outlook.low_tolerance_percent %);
 * - good — levněji byla nejvýš v outlook.good_share % dní;
 * - wait — blíží se výprodej, ve kterém byla loni levněji, nebo byla levněji
 *   aspoň v outlook.wait_share % dní;
 * - neutral — běžná cena.
 *
 * Hra hlídaná jen na PlayStationu doporučení nemá — historie je pro PC.
 * Historie kratší než outlook.min_history_days dní taky ne. Částky se
 * porovnávají v měně ITAD, ven jdou v měně zobrazení (R14).
 */
final class SaleOutlook
{
    private const DATE_FORMAT = 'Y-m-d';

    /** Rok — období statistik a loňský výprodej. */
    private const YEAR_DAYS = 365;

    private const PERCENT = 100;

    public function __construct(
        private readonly ItadHistory $history,
        private readonly ExchangeRates $rates,
    ) {}

    /**
     * Doporučení pro detail hry; null = bez dost dlouhé historie (nebo jen PlayStation).
     *
     * @return array<string, mixed>|null
     */
    public function build(Game $game, ?WatchlistItem $item, string $currency): ?array
    {
        if ($item?->platform === Platform::PlayStation) {
            return null;
        }

        $changes = $this->history->forGame($game);
        if ($changes === null || $changes === []) {
            return null;
        }

        $today = Game::today();
        $todayKey = $today->format(self::DATE_FORMAT);
        $lows = $this->dailyLows($changes, $today);
        $current = $lows[$todayKey] ?? null;
        $yearAgo = $today->subDays(self::YEAR_DAYS)->format(self::DATE_FORMAT);
        $year = array_filter($lows, fn (string $day): bool => $day >= $yearAgo && $day < $todayKey, ARRAY_FILTER_USE_KEY);
        $lowest = $this->lowest($year);
        if ($current === null || $lowest === null || count($year) < (int) config('games.outlook.min_history_days')) {
            return null;
        }

        $priceCurrency = $changes[0]->price->currency;
        $cheaperShare = intdiv(self::PERCENT * count(array_filter($year, fn (array $low): bool => $low['cents'] < $current['cents'])), count($year));
        $atLow = $current['cents'] * self::PERCENT <= $lowest['cents'] * (self::PERCENT + (int) config('games.outlook.low_tolerance_percent'));
        $nearLow = $this->nearLowPeriods([...$year, $todayKey => $current], min($lowest['cents'], $current['cents']));
        $lastNearLow = $this->lastFinished($nearLow, $todayKey);
        $upcoming = $this->upcomingEvent($changes, $today);

        [$verdict, $reason] = match (true) {
            $atLow => ['buy', 'low'],
            $cheaperShare <= (int) config('games.outlook.good_share') => ['good', 'cheap'],
            $upcoming !== null && ! $upcoming['ongoing'] && $upcoming['lastYearCents'] !== null && $upcoming['lastYearCents'] < $current['cents'] => ['wait', 'event'],
            $cheaperShare >= (int) config('games.outlook.wait_share') => ['wait', 'pattern'],
            default => ['neutral', 'typical'],
        };

        return [
            'verdict' => $verdict,
            'reason' => $reason,
            'currentCut' => $current['cut'],
            'cheaperShare' => $cheaperShare,
            'yearLow' => [
                'price' => $this->display($lowest['cents'], $priceCurrency, $currency),
                'cut' => $lowest['cut'],
                'date' => $lowest['day'],
            ],
            // U minima (do near_low_percent % nad ním): naposledy před kolika dny a typický odstup
            'nearLow' => [
                'now' => $nearLow !== [] && $nearLow[count($nearLow) - 1]['end'] === $todayKey,
                'daysAgo' => $lastNearLow === null ? null : (int) CarbonImmutable::parse($lastNearLow['end'], $today->getTimezone())->diffInDays($today),
                'intervalDays' => $this->median($this->gaps($nearLow)),
            ],
            'upcoming' => $upcoming === null ? null : [
                'key' => $upcoming['key'],
                'start' => $upcoming['start'],
                'end' => $upcoming['end'],
                'daysUntil' => $upcoming['daysUntil'],
                'ongoing' => $upcoming['ongoing'],
                'lastYear' => $upcoming['lastYearCents'] === null ? null : [
                    'price' => $this->display($upcoming['lastYearCents'], $priceCurrency, $currency),
                    'cut' => $upcoming['lastYearCut'],
                ],
            ],
            'since' => array_key_first($year),
            'nearLowPercent' => (int) config('games.outlook.near_low_percent'),
        ];
    }

    /**
     * Nejnižší cena každého dne od první změny do dneška a sleva té nabídky —
     * cena obchodu platí od změny do další, den bere nejnižší cenu, která
     * v něm platila (jako graf ceny).
     *
     * @param  list<ItadPriceChange>  $changes
     * @return array<string, array{cents: int, cut: int}> podle dne Y-m-d, vzestupně a bez mezer
     */
    private function dailyLows(array $changes, CarbonImmutable $today): array
    {
        $zone = $today->getTimezone();
        usort($changes, fn (ItadPriceChange $a, ItadPriceChange $b): int => $a->at <=> $b->at);

        $byDay = [];
        foreach ($changes as $change) {
            $byDay[$change->at->setTimezone($zone)->format(self::DATE_FORMAT)][] = $change;
        }

        $current = [];
        $lows = [];
        for ($day = CarbonImmutable::parse((string) array_key_first($byDay), $zone); $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $key = $day->format(self::DATE_FORMAT);
            $dayOffers = $current;
            foreach ($byDay[$key] ?? [] as $change) {
                $offer = ['cents' => $change->price->cents, 'cut' => $change->cut];
                if (! isset($dayOffers[$change->shopId]) || $offer['cents'] < $dayOffers[$change->shopId]['cents']) {
                    $dayOffers[$change->shopId] = $offer;
                }

                $current[$change->shopId] = $offer;
            }

            if ($dayOffers !== []) {
                usort($dayOffers, fn (array $a, array $b): int => [$a['cents'], -$a['cut']] <=> [$b['cents'], -$b['cut']]);
                $lows[$key] = $dayOffers[0];
            }
        }

        return $lows;
    }

    /**
     * Den s nejnižší cenou (při shodě nejnovější); null pro prázdné dny.
     *
     * @param  array<string, array{cents: int, cut: int}>  $lows
     * @return array{day: string, cents: int, cut: int}|null
     */
    private function lowest(array $lows): ?array
    {
        $lowest = null;
        foreach ($lows as $day => $low) {
            if ($lowest === null || $low['cents'] <= $lowest['cents']) {
                $lowest = ['day' => $day, ...$low];
            }
        }

        return $lowest;
    }

    /**
     * Souvislá období u minima — cena nejvýš near_low_percent % nad ním.
     *
     * @param  array<string, array{cents: int, cut: int}>  $lows  dny bez mezer
     * @return list<array{start: string, end: string}>
     */
    private function nearLowPeriods(array $lows, int $minimum): array
    {
        $limit = $minimum * (self::PERCENT + (int) config('games.outlook.near_low_percent'));
        $periods = [];
        $open = null;
        foreach ($lows as $day => $low) {
            if ($limit < $low['cents'] * self::PERCENT) {
                if ($open !== null) {
                    $periods[] = $open;
                    $open = null;
                }
            } else {
                $open = ['start' => $open['start'] ?? $day, 'end' => $day];
            }
        }

        if ($open !== null) {
            $periods[] = $open;
        }

        return $periods;
    }

    /**
     * Poslední období, které skončilo před dneškem; null = žádné.
     *
     * @param  list<array{start: string, end: string}>  $periods
     * @return array{start: string, end: string}|null
     */
    private function lastFinished(array $periods, string $today): ?array
    {
        $finished = array_values(array_filter($periods, fn (array $period): bool => $period['end'] < $today));

        return $finished === [] ? null : $finished[count($finished) - 1];
    }

    /**
     * Odstupy začátků po sobě jdoucích období ve dnech.
     *
     * @param  list<array{start: string, end: string}>  $periods
     * @return list<int>
     */
    private function gaps(array $periods): array
    {
        $gaps = [];
        for ($index = 1; $index < count($periods); $index++) {
            $gaps[] = (int) CarbonImmutable::parse($periods[$index - 1]['start'])->diffInDays(CarbonImmutable::parse($periods[$index]['start']));
        }

        return $gaps;
    }

    /**
     * Nejbližší výprodej z kalendáře (probíhající nebo začínající do
     * outlook.upcoming_days dní) v obchodě, ve kterém se hra prodává,
     * a nejnižší cena hry v tom obchodě ve stejném období před rokem.
     *
     * @param  list<ItadPriceChange>  $changes
     * @return array{key: string, start: string, end: string, daysUntil: int, ongoing: bool, lastYearCents: int|null, lastYearCut: int|null}|null
     */
    private function upcomingEvent(array $changes, CarbonImmutable $today): ?array
    {
        $zone = $today->getTimezone();
        $todayKey = $today->format(self::DATE_FORMAT);
        $horizon = $today->addDays((int) config('games.outlook.upcoming_days'))->format(self::DATE_FORMAT);

        /** @var list<array{key: string, shop_id: int, start: string, end: string}> $calendar */
        $calendar = config('games.sales_calendar');
        usort($calendar, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        foreach ($calendar as $event) {
            $shopChanges = array_values(array_filter($changes, fn (ItadPriceChange $change): bool => $change->shopId === $event['shop_id']));
            if ($event['end'] < $todayKey || $event['start'] > $horizon || $shopChanges === []) {
                continue;
            }

            $start = CarbonImmutable::parse($event['start'], $zone);
            $from = $start->subDays(self::YEAR_DAYS)->format(self::DATE_FORMAT);
            $to = CarbonImmutable::parse($event['end'], $zone)->subDays(self::YEAR_DAYS)->format(self::DATE_FORMAT);
            $lastYear = array_filter($this->dailyLows($shopChanges, $today), fn (string $day): bool => $day >= $from && $day <= $to, ARRAY_FILTER_USE_KEY);
            $lastYearLow = $this->lowest($lastYear);

            return [
                'key' => $event['key'],
                'start' => $event['start'],
                'end' => $event['end'],
                'daysUntil' => max(0, (int) $today->diffInDays($start)),
                'ongoing' => $event['start'] <= $todayKey,
                'lastYearCents' => $lastYearLow['cents'] ?? null,
                'lastYearCut' => $lastYearLow['cut'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Medián (zaokrouhlený dolů); null pro prázdný seznam.
     *
     * @param  list<int>  $values
     */
    private function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : intdiv($values[$middle - 1] + $values[$middle], 2);
    }

    /**
     * Částka v měně zobrazení; bez kurzu v měně ITAD.
     *
     * @return array{amount: float, currency: string}
     */
    private function display(int $cents, string $priceCurrency, string $currency): array
    {
        $money = new Money($cents, $priceCurrency);

        return ($this->rates->convert($money, $currency) ?? $money)->toArray();
    }
}
