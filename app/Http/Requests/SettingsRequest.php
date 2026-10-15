<?php

/**
 * Nastavení upozornění účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EmailFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jak často chodí e-mail (R53), týdenní souhrn (R36) a předplatné PS Plus
 * (R26) — volby vždy celé.
 */
class SettingsRequest extends FormRequest
{
    /**
     * Měnit smí každý přihlášený — jen svůj účet.
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
            'email_frequency' => ['required', Rule::enum(EmailFrequency::class)],
            'notify_weekly' => ['required', 'boolean'],
            'has_ps_plus' => ['required', 'boolean'],
            // Nepovinné kvůli starší stránce v prohlížeči; chybí = nemám
            'has_game_pass' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Vlastní hláška — aplikace nemá přeložené validační texty Laravelu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email_frequency' => __('app.validation.email_frequency'),
        ];
    }

    /**
     * Zvolená frekvence e-mailu.
     */
    public function emailFrequency(): EmailFrequency
    {
        return EmailFrequency::from((string) $this->validated('email_frequency'));
    }
}
