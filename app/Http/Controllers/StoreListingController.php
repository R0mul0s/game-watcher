<?php

/**
 * Připojení a odpojení produktu PlayStation Store nebo krabicové hry u hry.
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
use App\Domain\Retail\Actions\ConnectRetailProduct;
use App\Domain\Retail\Exceptions\RetailProductNotFound;
use App\Domain\Retail\Exceptions\RetailUnavailable;
use App\Domain\Retail\RetailUrl;
use App\Http\Requests\StoreListingRequest;
use App\Models\Game;
use App\Models\StoreListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Obě akce vrací uživatele na detail hry s hláškou (R20). Připojit jde produkt
 * PS Store i krabicovou hru z Xzone nebo Heureky (R52) — podle hostitele odkazu.
 */
class StoreListingController extends Controller
{
    /**
     * Připojí produkt podle vloženého odkazu a hned stáhne jeho cenu.
     *
     * @throws ValidationException
     */
    public function store(StoreListingRequest $request, Game $game, ConnectPlayStationProduct $connect, ConnectRetailProduct $connectRetail): RedirectResponse
    {
        // Krabicová hra z Xzone nebo Heureky (R52), jinak PlayStation Store
        $retailUrl = RetailUrl::parse($request->string('url')->toString());
        if ($retailUrl !== null) {
            return $this->storeRetail($game, $retailUrl, $connectRetail);
        }

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
     * Připojí krabicovou hru z obchodu (R52) a hned stáhne její cenu.
     *
     * @throws ValidationException
     */
    private function storeRetail(Game $game, RetailUrl $url, ConnectRetailProduct $connect): RedirectResponse
    {
        try {
            $listing = $connect->handle($game, $url);
        } catch (RetailProductNotFound) {
            throw ValidationException::withMessages(['url' => __('app.listings.not_retail')]);
        } catch (RetailUnavailable) {
            return Inertia::flash('error', __('app.listings.retail_unavailable'))->back();
        }

        return Inertia::flash('success', __('app.listings.connected_retail', [
            'name' => $listing->name,
            'shop' => __('app.listings.shops.'.$url->source->value),
        ]))->back();
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
