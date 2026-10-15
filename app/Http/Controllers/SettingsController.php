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
use App\Enums\EmailFrequency;
use App\Http\Requests\SettingsRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kanály upozornění (R13, R26, R53): jak často chodí e-mail na adresu účtu
 * (souhrn ráno, hned, vůbec) a zařízení s Web Push (to si stránka zapíná sama
 * přes PushSubscriptionController); centrum upozornění se nevypíná. Volba PS
 * Plus říká, jestli upozornění počítají cenu pro předplatitele.
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
                'emailFrequency' => $user->email_frequency->value,
                'emailFrequencies' => array_map(fn (EmailFrequency $frequency): string => $frequency->value, EmailFrequency::cases()),
                'digestHour' => (int) config('games.alerts.digest_hour'),
                'notifyWeekly' => $user->notify_weekly,
                'hasPsPlus' => $user->has_ps_plus,
                'hasGamePass' => $user->has_game_pass,
                'devices' => $user->pushSubscriptions()->count(),
            ],
            // Bez klíčů VAPID se upozornění na zařízení nenabízí
            'push' => PushSender::isEnabled() ? [
                'publicKey' => (string) config('games.push.public_key'),
                'subscribeUrl' => route('push.store', absolute: false),
                'testUrl' => route('push.test', absolute: false),
            ] : null,
            'updateUrl' => route('settings.update', absolute: false),
            // Stav zdrojů (R29) — v hlavní navigaci se na mobilu nevejde
            'sourcesUrl' => route('sources.show', absolute: false),
        ]);
    }

    /**
     * Uloží volby upozornění.
     */
    public function update(SettingsRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $frequency = $request->emailFrequency();

        $user->update([
            'email_frequency' => $frequency,
            'notify_weekly' => $request->boolean('notify_weekly'),
            'has_ps_plus' => $request->boolean('has_ps_plus'),
            'has_game_pass' => $request->boolean('has_game_pass'),
            // Po zapnutí e-mailu jen nová upozornění — ne všechno, co se mezitím
            // nastřádalo v centru (R53)
            ...($user->email_frequency === EmailFrequency::Off && $frequency !== EmailFrequency::Off
                ? ['alert_email_sent_at' => CarbonImmutable::now()]
                : []),
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
