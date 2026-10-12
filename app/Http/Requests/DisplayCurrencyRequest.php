<?php

/**
 * Změna měny zobrazení cen.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Jen měny z games.prices.display_currencies (R14).
 */
class DisplayCurrencyRequest extends FormRequest
{
    /**
     * Měnit smí každý přihlášený svou vlastní volbu (middleware `auth`).
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
            'currency' => ['required', 'string', Rule::in((array) config('games.prices.display_currencies'))],
        ];
    }
}
