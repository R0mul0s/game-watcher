<?php

/**
 * Připojení a odpojení produktu PlayStation Store u hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Domain\PlayStation\Actions\DisconnectStoreListing;
use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Http\Requests\StoreListingRequest;
use App\Models\Game;
use App\Models\StoreListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Obě akce vrací uživatele na detail hry s hláškou (R20).
 */
class StoreListingController extends Controller
{
    /**
     * Připojí produkt podle vloženého odkazu a hned stáhne jeho cenu.
     *
     * @throws ValidationException
     */
    public function store(StoreListingRequest $request, Game $game, ConnectPlayStationProduct $connect): RedirectResponse
    {
        try {
            $listing = $connect->handle($game, $request->string('url')->toString());
        } catch (PlayStationProductNotFound) {
            throw ValidationException::withMessages(['url' => __('app.listings.not_playstation')]);
        } catch (PlayStationUnavailable) {
            return Inertia::flash('error', __('app.listings.unavailable'))->back();
        }

        return Inertia::flash('success', __('app.listings.connected', ['name' => $listing->name]))->back();
    }

    /**
     * Odpojí produkt (jeho cena z výpisu zmizí).
     */
    public function destroy(Game $game, StoreListing $storeListing, DisconnectStoreListing $disconnect): RedirectResponse
    {
        $disconnect->handle($storeListing);

        return Inertia::flash('success', __('app.listings.disconnected', ['name' => $storeListing->name]))->back();
    }
}
