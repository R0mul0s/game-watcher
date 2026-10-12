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

use App\Domain\Alerts\Actions\SendPriceAlerts;
use App\Domain\Catalog\Actions\RefreshGameDetails;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Maintenance\PruneExpiredCache;
use App\Domain\Metacritic\Actions\RefreshMetascores;
use App\Domain\Monitoring\SourceMonitor;
use App\Domain\Pricing\Actions\RefreshPrices;
use App\Domain\Pricing\PriceRefreshResult;
use App\Enums\MonitoredSource;
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
 * Výsledek každého zdroje i celého běhu zapisuje stav zdrojů (R29).
 */
class CronController extends Controller
{
    /** Klíč zámku proti souběžnému běhu. */
    private const LOCK_KEY = 'cron:run';

    /** Den lístku ČNB v odpovědi („9. 10. 2026“). */
    private const DAY_FORMAT = 'j. n. Y';

    /** Oddělovač kroků ve zprávě o neúspěšném běhu. */
    private const STEP_SEPARATOR = ', ';

    public function __construct(private readonly SourceMonitor $monitor) {}

    /**
     * Provede kroky a vrátí souhrn jako prostý text — 500, když některý krok
     * selhal, 409, když ještě běží předchozí volání.
     */
    public function __invoke(
        CronRequest $request,
        ImportExchangeRates $importExchangeRates,
        RefreshPrices $refreshPrices,
        RefreshGameDetails $refreshGameDetails,
        RefreshMetascores $refreshMetascores,
        SendPriceAlerts $sendPriceAlerts,
        PruneExpiredCache $pruneExpiredCache,
    ): Response {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('games.cron.lock_seconds'));
        if (! $lock->get()) {
            return $this->plainText(__('app.cron.already_running'), Response::HTTP_CONFLICT);
        }

        try {
            /** @var list<string> $failedSteps klíče kroků (app.cron.steps), které selhaly */
            $failedSteps = [];

            $lines = [
                // Kurzy před cenami — přehled hned převádí novým kurzem (R14)
                $this->attempt('exchange_rates', [MonitoredSource::ExchangeRates], $failedSteps, function () use ($importExchangeRates): string {
                    $day = $importExchangeRates->handle();
                    $this->monitor->succeeded(MonitoredSource::ExchangeRates);

                    return $day === null
                        ? __('app.cron.exchange_rates_current')
                        : __('app.cron.exchange_rates_imported', ['day' => $day->format(self::DAY_FORMAT)]);
                }),
                $this->attempt('prices', self::priceSources(), $failedSteps, function () use ($refreshPrices, &$failedSteps): string {
                    $result = $refreshPrices->watched();
                    $this->recordPrices($result);
                    // Výpadek zdroje zalogoval klient; tady jen označí běh jako neúspěšný
                    if ($result->hasFailures()) {
                        $failedSteps[] = 'prices';
                    }

                    // U zdroje, který selhal, místo počtu „chyba“
                    $count = fn (PriceSource $source): int|string => $result->hasFailed($source)
                        ? (string) __('app.cron.source_failed')
                        : $result->updatedBy($source);

                    return __('app.cron.prices', [
                        'itad' => $count(PriceSource::Itad),
                        'ggdeals' => $count(PriceSource::GgDeals),
                        'playstation' => $count(PriceSource::PlayStation),
                    ]);
                }),
                // Žánry a režimy pro filtry (R25) — doplňkové údaje, výpadek Steamu
                // běh neoznačí jako chybný, zbytek dožene další běh
                $this->attempt('details', [MonitoredSource::Steam], $failedSteps, function () use ($refreshGameDetails): string {
                    $pending = $refreshGameDetails->pending();
                    [$count, $steamFailed] = $refreshGameDetails->handle($pending);
                    $steamFailed
                        ? $this->monitor->failed(MonitoredSource::Steam, (string) __('app.monitoring.steam_failed'))
                        : $this->monitor->succeeded(MonitoredSource::Steam, $count, $pending->count());

                    return __($steamFailed ? 'app.cron.details_failed' : 'app.cron.details', ['count' => $count]);
                }),
                // Metascore her, které ITAD nemá (R33) — doplňkové údaje jako žánry
                $this->attempt('metacritic', [MonitoredSource::Metacritic], $failedSteps, function () use ($refreshMetascores): string {
                    $pending = $refreshMetascores->pending();
                    [$count, $error] = $refreshMetascores->handle($pending);
                    $error === null
                        ? $this->monitor->succeeded(MonitoredSource::Metacritic, $count, $pending->count())
                        : $this->monitor->failed(MonitoredSource::Metacritic, $error);

                    return __($error === null ? 'app.cron.metacritic' : 'app.cron.metacritic_failed', ['count' => $count]);
                }),
                // Upozornění až po cenách (R26) — porovnávají čerstvé ceny. Nedoručené
                // upozornění (chyba SMTP i push) označí běh jako chybný, zkusí ho další běh
                $this->attempt('alerts', [MonitoredSource::Alerts], $failedSteps, function () use ($sendPriceAlerts, &$failedSteps): string {
                    $result = $sendPriceAlerts->handle();
                    if ($result->undelivered > 0) {
                        $failedSteps[] = 'alerts';
                        $this->monitor->failed(MonitoredSource::Alerts, (string) __('app.monitoring.alerts_undelivered', ['count' => $result->undelivered]));
                    } else {
                        $this->monitor->succeeded(MonitoredSource::Alerts, $result->alerts);
                    }

                    return __('app.cron.alerts', [
                        'alerts' => $result->alerts,
                        'emails' => $result->emails,
                        'pushes' => $result->pushes,
                    ]).($result->undelivered > 0 ? __('app.cron.alerts_undelivered', ['count' => $result->undelivered]) : '');
                }),
                $this->attempt('cache', [], $failedSteps, fn (): string => __('app.cron.cache_pruned', ['count' => $pruneExpiredCache->handle()])),
            ];

            $this->recordRun($failedSteps);

            return $this->plainText(implode("\n", $lines), $failedSteps === [] ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            $lock->release();
        }
    }

    /**
     * Provede krok a vrátí jeho řádek souhrnu. Výjimku zapíše do logu,
     * zdrojům kroku jako chybu a místo souhrnu vrátí řádek o chybě kroku.
     *
     * @param  string  $step  Klíč kroku v app.cron.steps
     * @param  list<MonitoredSource>  $sources  zdroje kroku pro stav zdrojů
     * @param  list<string>  $failedSteps  klíče kroků, které selhaly
     * @param  Closure(): string  $task
     */
    private function attempt(string $step, array $sources, array &$failedSteps, Closure $task): string
    {
        try {
            return $task();
        } catch (Throwable $e) {
            report($e);
            $failedSteps[] = $step;
            foreach ($sources as $source) {
                $this->monitor->failed($source, $this->monitor->describe($e));
            }

            return __('app.cron.step_failed', ['step' => __('app.cron.steps.'.$step)]);
        }
    }

    /**
     * Zapíše výsledek zdrojů cen do stavu zdrojů.
     */
    private function recordPrices(PriceRefreshResult $result): void
    {
        foreach (PriceSource::cases() as $source) {
            $result->hasFailed($source)
                ? $this->monitor->failed(MonitoredSource::fromPriceSource($source), $result->errorOf($source))
                : $this->monitor->succeeded(MonitoredSource::fromPriceSource($source), $result->updatedBy($source), $result->expectedBy($source));
        }
    }

    /**
     * Zapíše běh cronu jako celek — u neúspěchu se seznamem kroků, které selhaly.
     *
     * @param  list<string>  $failedSteps  klíče kroků v app.cron.steps
     */
    private function recordRun(array $failedSteps): void
    {
        if ($failedSteps === []) {
            $this->monitor->succeeded(MonitoredSource::Cron);

            return;
        }

        $steps = array_map(fn (string $step): string => (string) __('app.cron.steps.'.$step), array_unique($failedSteps));
        $this->monitor->failed(MonitoredSource::Cron, (string) __('app.monitoring.cron_failed', ['steps' => implode(self::STEP_SEPARATOR, $steps)]));
    }

    /**
     * Zdroje kroku „ceny“.
     *
     * @return list<MonitoredSource>
     */
    private static function priceSources(): array
    {
        return array_map(MonitoredSource::fromPriceSource(...), PriceSource::cases());
    }

    /**
     * Odpověď cronu — prostý text, který se nesmí cachovat.
     */
    private function plainText(string $text, int $status): Response
    {
        return response($text, $status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
