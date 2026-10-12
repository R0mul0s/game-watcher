<?php

/**
 * Připojení stránky hry na Metacritic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Metacritic\Actions\LinkMetacritic;
use App\Domain\Metacritic\Exceptions\MetacriticGameNotFound;
use App\Domain\Metacritic\Exceptions\MetacriticUnavailable;
use App\Http\Requests\MetacriticLinkRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Odkaz od uživatele, když stránka odvozená z názvu nesedí (R33) — vrací na
 * detail hry s hláškou jako připojení PS Store (R20).
 */
class MetacriticController extends Controller
{
    /**
     * Připojí stránku a hned uloží Metascore.
     *
     * @throws ValidationException
     */
    public function update(MetacriticLinkRequest $request, Game $game, LinkMetacritic $link): RedirectResponse
    {
        try {
            $page = $link->handle($game, $request->string('url')->toString());
        } catch (MetacriticGameNotFound) {
            throw ValidationException::withMessages(['url' => __('app.metacritic.not_found')]);
        } catch (MetacriticUnavailable) {
            return Inertia::flash('error', __('app.metacritic.unavailable'))->back();
        }

        return Inertia::flash('success', $page->score === null
            ? __('app.metacritic.linked_unrated', ['name' => $page->name])
            : __('app.metacritic.linked', ['score' => $page->score]))->back();
    }
}
