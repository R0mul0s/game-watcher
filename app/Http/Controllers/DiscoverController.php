<?php

/**
 * Stránka Objevit — slevy her podle štítků sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Discover\DiscoverDeals;
use App\Http\Requests\DiscoverRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Slevy z ITAD (R43) — hry, které uživatel ještě nesleduje, jde rovnou přidat.
 * Výpadek ITAD ukáže jako hlášku.
 */
class DiscoverController extends Controller
{
    /**
     * Stránka se slevami a filtry.
     */
    public function __invoke(DiscoverRequest $request, DiscoverDeals $discoverDeals): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->filters();

        return Inertia::render('Discover', [
            ...$discoverDeals->build($user, $filters, $user->displayCurrency()),
            'filters' => $filters->toArray(),
            'discoverUrl' => route('discover', absolute: false),
            'watchUrl' => route('watchlist.store', absolute: false),
        ]);
    }
}
