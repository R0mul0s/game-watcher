<?php

/**
 * Připojení hry na HowLongToBeat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Hltb\Actions\LinkHltb;
use App\Domain\Hltb\Exceptions\HltbGameNotFound;
use App\Domain\Hltb\Exceptions\HltbUnavailable;
use App\Http\Requests\HltbLinkRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Odkaz od uživatele, když hledání podle názvu hru nenašlo (R44) — vrací na
 * detail hry s hláškou jako připojení Metacritic (R33).
 */
class HltbController extends Controller
{
    /**
     * Připojí hru a hned uloží délky hraní.
     *
     * @throws ValidationException
     */
    public function update(HltbLinkRequest $request, Game $game, LinkHltb $link): RedirectResponse
    {
        try {
            $hltb = $link->handle($game, $request->string('url')->toString());
        } catch (HltbGameNotFound) {
            throw ValidationException::withMessages(['url' => __('app.hltb.not_found')]);
        } catch (HltbUnavailable) {
            return Inertia::flash('error', __('app.hltb.unavailable'))->back();
        }

        return Inertia::flash('success', __('app.hltb.linked', ['name' => $hltb->name]))->back();
    }
}
