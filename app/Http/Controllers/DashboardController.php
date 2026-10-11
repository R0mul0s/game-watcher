<?php

/**
 * Přehled sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Úvodní stránka po přihlášení. Seznam sledovaných her a jejich cen přibude
 * s etapami 2 a 3 (docs/PLAN.md) — zatím jen prázdný stav.
 */
class DashboardController extends Controller
{
    /**
     * Vykreslí přehled.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard');
    }
}
