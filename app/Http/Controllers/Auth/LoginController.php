<?php

/**
 * Přihlášení a odhlášení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Celá aplikace je za přihlášením (R3), registrace ani obnova hesla e-mailem
 * nejsou (R4).
 */
class LoginController extends Controller
{
    /**
     * Přihlašovací stránka.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'loginUrl' => route('login.store', absolute: false),
        ]);
    }

    /**
     * Ověří údaje, obnoví session (ochrana proti session fixation) a pošle
     * uživatele tam, kam původně mířil.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Odhlásí uživatele a zneplatní session i CSRF token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
