<?php

/**
 * Připojení produktu obchodu ke hře odkazem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Jen tvar a délka — hostitele a tvar adresy ověří doména (PlayStationUrl),
 * aby server nevolal libovolnou adresu (SSRF).
 */
class StoreListingRequest extends FormRequest
{
    /** Nejdelší vložený odkaz. */
    public const MAX_URL_LENGTH = 500;

    /**
     * Propojovat smí každý přihlášený — katalog je společný (R11).
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
            'url' => ['required', 'string', 'max:'.self::MAX_URL_LENGTH],
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
            'url.required' => __('app.listings.url_required'),
            'url.max' => __('app.listings.not_playstation'),
        ];
    }
}
