<?php

/**
 * Nastavení účtu — upozornění na ceny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Alerts\PushSender;
use App\Http\Requests\SettingsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kanály upozornění (R13, R26): e-mail na adresu účtu a zařízení s Web Push
 * (to si stránka zapíná sama přes PushSubscriptionController). Volba PS Plus
 * říká, jestli upozornění počítají cenu pro předplatitele.
 */
class SettingsController extends Controller
{
    /**
     * Stránka nastavení.
     */
    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Settings', [
            'settings' => [
                'email' => $user->email,
                'notifyEmail' => $user->notify_email,
                'hasPsPlus' => $user->has_ps_plus,
                'devices' => $user->pushSubscriptions()->count(),
            ],
            // Bez klíčů VAPID se upozornění na zařízení nenabízí
            'push' => PushSender::isEnabled() ? [
                'publicKey' => (string) config('games.push.public_key'),
                'subscribeUrl' => route('push.store', absolute: false),
                'testUrl' => route('push.test', absolute: false),
            ] : null,
            'updateUrl' => route('settings.update', absolute: false),
        ]);
    }

    /**
     * Uloží volby upozornění.
     */
    public function update(SettingsRequest $request): RedirectResponse
    {
        $this->user($request)->update([
            'notify_email' => $request->boolean('notify_email'),
            'has_ps_plus' => $request->boolean('has_ps_plus'),
        ]);

        return Inertia::flash('success', __('app.alerts.settings_saved'))->back();
    }

    /**
     * Přihlášený uživatel — routy jsou za middlewarem `auth`.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
