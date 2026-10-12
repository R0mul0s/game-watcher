<?php

/**
 * Stránka stavu zdrojů cen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\SourceHealth;
use App\Domain\Monitoring\SourceMonitor;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kdy se který zdroj naposledy povedl, kolik toho stáhl a poslední chyba
 * (R29) — změna formátu obchodu se pozná tady, ne až podle chybějících cen.
 */
class SourceStatusController extends Controller
{
    /**
     * Vykreslí stav zdrojů.
     */
    public function __invoke(SourceMonitor $monitor): Response
    {
        return Inertia::render('Sources', [
            'sources' => array_map(fn (SourceHealth $health): array => $health->toArray(), $monitor->report()),
        ]);
    }
}
