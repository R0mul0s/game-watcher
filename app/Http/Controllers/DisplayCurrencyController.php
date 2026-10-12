<?php

/**
 * Přepínač měny zobrazení cen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DisplayCurrencyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Volba se ukládá u účtu, aby platila na všech zařízeních (R14); stránka se
 * vrátí tam, kde uživatel byl, už s cenami v nové měně.
 */
class DisplayCurrencyController extends Controller
{
    /**
     * Uloží měnu zobrazení.
     */
    public function __invoke(DisplayCurrencyRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->update(['display_currency' => $request->string('currency')->toString()]);
        }

        return back();
    }
}
