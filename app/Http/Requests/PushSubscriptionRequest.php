<?php

/**
 * Odběr upozornění na zařízení (Web Push).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PushEndpoint;
use App\Rules\PushKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tvar PushSubscription.toJSON() z prohlížeče a kódování obsahu; adresa jen
 * u známé push služby (SSRF), klíče ve správném tvaru. Převzato z Počasí.
 */
class PushSubscriptionRequest extends FormRequest
{
    private const CONTENT_ENCODINGS = ['aes128gcm', 'aesgcm'];

    /**
     * Odebírat smí každý přihlášený (middleware `auth`).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pravidla.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:'.PushEndpoint::MAX_LENGTH, new PushEndpoint],
            'keys.p256dh' => ['required', 'string', new PushKey(PushKey::PUBLIC_KEY_BYTES)],
            'keys.auth' => ['required', 'string', new PushKey(PushKey::AUTH_BYTES)],
            'content_encoding' => ['required', Rule::in(self::CONTENT_ENCODINGS)],
        ];
    }

    /**
     * Odběr ve tvaru sloupců push_subscriptions.
     *
     * @return array{endpoint: string, public_key: string, auth_token: string, content_encoding: string}
     */
    public function subscription(): array
    {
        return [
            'endpoint' => $this->string('endpoint')->value(),
            'public_key' => $this->string('keys.p256dh')->value(),
            'auth_token' => $this->string('keys.auth')->value(),
            'content_encoding' => $this->string('content_encoding')->value(),
        ];
    }
}
