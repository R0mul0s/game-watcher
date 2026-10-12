<?php

/**
 * Stav zdrojů pro externí monitoring (UptimeRobot).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\SourceHealth;
use App\Domain\Monitoring\SourceMonitor;
use App\Enums\MonitoredSource;
use App\Enums\SourceState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * Veřejná URL bez tokenu (R29, vzor /health/data z Počasí) — prozrazuje jen,
 * jak dávno se který zdroj povedl. 200 = vše v pořádku, 503 = výpadek cronu
 * nebo hlídaného zdroje (monitoring pošle upozornění). Oddělená od cron URL,
 * aby kontrola každých 5 minut nespouštěla stahování.
 */
class HealthSourcesController extends Controller
{
    /**
     * Vrátí stav zdrojů jako prostý text, řádek na zdroj, se stavovým kódem 200 nebo 503.
     */
    public function __invoke(SourceMonitor $monitor): Response
    {
        $now = CarbonImmutable::now();
        $anyDown = false;
        $lines = [];

        foreach ($monitor->report() as $health) {
            $anyDown = $anyDown || ($health->source->isCritical() && $health->state->isDown());
            $lines[] = __('app.ui.sources.names.'.$health->source->value).' — '.$this->describe($health, $now);
        }

        return response(
            implode("\n", $lines),
            $anyDown ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    /**
     * Řádek jednoho zdroje — stav a stáří posledního úspěchu (u cronu běhu).
     */
    private function describe(SourceHealth $health, CarbonImmutable $now): string
    {
        $minutes = $health->referenceAt() === null ? null : (int) $health->referenceAt()->diffInMinutes($now, absolute: true);
        $prefix = $health->source->isCritical() ? '' : __('app.monitoring.health.optional');
        // U cronu se počítá od posledního běhu, ne úspěchu — vlastní texty
        $key = 'app.monitoring.health.'.($health->source === MonitoredSource::Cron ? 'cron_' : '');

        return $prefix.match (true) {
            $health->state === SourceState::Never => __('app.monitoring.health.never'),
            $minutes === null => __('app.monitoring.health.never_succeeded'),
            default => __($key.$health->state->value, ['minutes' => $minutes]),
        };
    }
}
