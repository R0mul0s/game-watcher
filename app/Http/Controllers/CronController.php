<?php

/**
 * URL pro cron: kurzy ČNB, ceny her a úklid.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Maintenance\PruneExpiredCache;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Enums\PriceSource;
use App\Http\Requests\CronRequest;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cron ve WebAdminu umí jen zavolat URL, artisan nespustí (R2). Kroky na sobě
 * nezávisí: chyba jednoho se zaloguje a ostatní proběhnou, URL pak vrátí 500
 * (pro monitoring). Souběžné volání zastaví zámek — vzor z projektu Počasí.
 */
class CronController extends Controller
{
    /** Klíč zámku proti souběžnému běhu. */
    private const LOCK_KEY = 'cron:run';

    /** Den lístku ČNB v odpovědi („9. 10. 2026“). */
    private const DAY_FORMAT = 'j. n. Y';

    /**
     * Provede kroky a vrátí souhrn jako prostý text — 500, když některý krok
     * selhal, 409, když ještě běží předchozí volání.
     */
    public function __invoke(
        CronRequest $request,
        ImportExchangeRates $importExchangeRates,
        RefreshPrices $refreshPrices,
        PruneExpiredCache $pruneExpiredCache,
    ): Response {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('games.cron.lock_seconds'));
        if (! $lock->get()) {
            return $this->plainText(__('app.cron.already_running'), Response::HTTP_CONFLICT);
        }

        try {
            $failed = false;

            $lines = [
                // Kurzy před cenami — přehled hned převádí novým kurzem (R14)
                $this->attempt('exchange_rates', $failed, function () use ($importExchangeRates): string {
                    $day = $importExchangeRates->handle();

                    return $day === null
                        ? __('app.cron.exchange_rates_current')
                        : __('app.cron.exchange_rates_imported', ['day' => $day->format(self::DAY_FORMAT)]);
                }),
                $this->attempt('prices', $failed, function () use ($refreshPrices, &$failed): string {
                    $result = $refreshPrices->watched();
                    // Výpadek zdroje zalogoval klient; tady jen označí běh jako neúspěšný
                    $failed = $failed || $result->hasFailures();

                    return __('app.cron.prices', [
                        'itad' => in_array(PriceSource::Itad, $result->failed, true) ? (string) __('app.cron.source_failed') : $result->updatedBy(PriceSource::Itad),
                        'ggdeals' => in_array(PriceSource::GgDeals, $result->failed, true) ? (string) __('app.cron.source_failed') : $result->updatedBy(PriceSource::GgDeals),
                    ]);
                }),
                $this->attempt('cache', $failed, fn (): string => __('app.cron.cache_pruned', ['count' => $pruneExpiredCache->handle()])),
            ];

            return $this->plainText(implode("\n", $lines), $failed ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK);
        } finally {
            $lock->release();
        }
    }

    /**
     * Provede krok a vrátí jeho řádek souhrnu. Výjimku zapíše do logu,
     * nastaví $failed a místo souhrnu vrátí řádek o chybě kroku.
     *
     * @param  string  $step  Klíč kroku v app.cron.steps
     * @param  Closure(): string  $task
     */
    private function attempt(string $step, bool &$failed, Closure $task): string
    {
        try {
            return $task();
        } catch (Throwable $e) {
            report($e);
            $failed = true;

            return __('app.cron.step_failed', ['step' => __('app.cron.steps.'.$step)]);
        }
    }

    /**
     * Odpověď cronu — prostý text, který se nesmí cachovat.
     */
    private function plainText(string $text, int $status): Response
    {
        return response($text, $status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
