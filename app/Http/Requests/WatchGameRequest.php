<?php

/**
 * Přidání hry na seznam sledovaných.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hra se určuje ITAD ID z výsledku hledání (R10) — jen tvar UUID, existenci
 * ověří až ITAD.
 */
class WatchGameRequest extends FormRequest
{
    /**
     * Přidávat smí každý přihlášený (middleware `auth`).
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
            'itad_id' => ['required', 'uuid'],
        ];
    }

    /**
     * České hlášky.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'itad_id.required' => __('app.catalog.game_not_found'),
            'itad_id.uuid' => __('app.catalog.game_not_found'),
        ];
    }
}
