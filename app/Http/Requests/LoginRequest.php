<?php

/**
 * Přihlášení e-mailem a heslem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ověření údajů a ochrana proti hádání hesla (R4) podle vzoru startovacích
 * sad Laravelu: pokusy se počítají na kombinaci e-mailu a IP, po
 * games.auth.max_attempts neúspěších je přihlášení na chvíli zablokované.
 */
class LoginRequest extends FormRequest
{
    /**
     * Přihlásit se smí každý (middleware `guest` pustí jen nepřihlášené).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pravidla formuláře.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * České hlášky — aplikace nemá přeložené validační texty Laravelu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('app.auth.email_required'),
            'email.email' => __('app.auth.email_invalid'),
            'password.required' => __('app.auth.password_required'),
        ];
    }

    /**
     * Přihlásí uživatele, nebo vyhodí chybu formuláře (špatné údaje, zablokováno).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), (int) config('games.auth.lockout_seconds'));

            throw ValidationException::withMessages([
                'email' => __('app.auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Po vyčerpání pokusů vyhodí chybu s počtem sekund do dalšího pokusu.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), (int) config('games.auth.max_attempts'))) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('app.auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    /**
     * Klíč počítadla pokusů — e-mail (bez ohledu na velikost písmen) a IP.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
