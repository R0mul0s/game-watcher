<?php

/**
 * Stažení denních kurzů ČNB.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Currency\Actions;

use App\Domain\Currency\CnbRateSheet;
use App\Domain\Currency\Exceptions\ExchangeRatesUnavailable;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Volá ho cron URL (R14). ČNB vyhlašuje kurzy v pracovní dny odpoledne,
 * o víkendu a svátcích platí poslední lístek — proto se stahuje, jen když
 * uložený kurz není dnešní, a nejvýš jednou za exchange_rates.retry_minutes.
 */
final class ImportExchangeRates
{
    private const ATTEMPT_KEY = 'exchange-rates:last-attempt';

    private const SECONDS_PER_MINUTE = 60;

    /**
     * Den nově uloženého lístku; null, když nebylo potřeba nebo se lístek nezměnil.
     *
     * @throws ExchangeRatesUnavailable
     */
    public function handle(): ?CarbonImmutable
    {
        $today = CarbonImmutable::now((string) config('games.display_timezone'))->startOfDay();
        $latest = ExchangeRate::query()->max('day');
        if ($latest !== null && CarbonImmutable::parse((string) $latest)->greaterThanOrEqualTo($today->toDateString())) {
            return null;
        }

        if (Cache::has(self::ATTEMPT_KEY)) {
            return null;
        }
        Cache::put(self::ATTEMPT_KEY, true, (int) config('games.exchange_rates.retry_minutes') * self::SECONDS_PER_MINUTE);

        $sheet = CnbRateSheet::parse($this->download());
        if ($latest !== null && $sheet->day->toDateString() === CarbonImmutable::parse((string) $latest)->toDateString()) {
            return null;
        }

        ExchangeRate::query()->upsert(
            array_map(fn (string $currency, string $rate): array => [
                'day' => $sheet->day->toDateString(),
                'currency' => $currency,
                'rate' => $rate,
            ], array_keys($sheet->rates), $sheet->rates),
            ['day', 'currency'],
            ['rate'],
        );

        return $sheet->day;
    }

    /**
     * Text lístku z ČNB.
     *
     * @throws ExchangeRatesUnavailable
     */
    private function download(): string
    {
        try {
            $response = Http::timeout((int) config('games.exchange_rates.timeout_seconds'))
                ->get((string) config('games.exchange_rates.url'));
        } catch (ConnectionException $e) {
            throw new ExchangeRatesUnavailable('ČNB neodpovídá: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new ExchangeRatesUnavailable("ČNB vrátila HTTP {$response->status()}.");
        }

        return $response->body();
    }
}
