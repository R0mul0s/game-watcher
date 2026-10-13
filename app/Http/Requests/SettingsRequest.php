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

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kanál e-mail, týdenní souhrn (R36) a předplatné PS Plus (R26) — volby vždy celé.
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'notify_email' => ['required', 'boolean'],
            'notify_weekly' => ['required', 'boolean'],
            'has_ps_plus' => ['required', 'boolean'],
        ];
    }
}
