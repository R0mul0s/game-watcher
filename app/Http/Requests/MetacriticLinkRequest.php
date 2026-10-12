<?php

/**
 * Odkaz na stránku hry na Metacritic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Jen tvar a délka — hostitele a tvar adresy ověří doména (MetacriticUrl),
 * aby server nevolal libovolnou adresu (SSRF, R33).
 */
class MetacriticLinkRequest extends FormRequest
{
    /**
     * Připojovat smí každý přihlášený — katalog je společný (R11).
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
            'url' => ['required', 'string', 'max:'.StoreListingRequest::MAX_URL_LENGTH],
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
            'url.required' => __('app.metacritic.url_required'),
            'url.max' => __('app.metacritic.not_found'),
        ];
    }
}
