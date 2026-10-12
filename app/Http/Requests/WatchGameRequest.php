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

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hra se určuje ITAD ID z výsledku hledání (R10) — jen tvar UUID, existenci
 * ověří až ITAD. Volitelně hlídaná platforma a odkaz na PS Store (R24);
 * hostitele odkazu ověří až PlayStationUrl (SSRF).
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'itad_id' => ['required', 'uuid'],
            'platform' => ['nullable', Rule::enum(Platform::class)],
            'listing_url' => ['nullable', 'string', 'max:'.StoreListingRequest::MAX_URL_LENGTH],
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
            'listing_url.max' => __('app.listings.not_playstation'),
        ];
    }

    /**
     * Hlídaná platforma (R24); null = všude.
     */
    public function platform(): ?Platform
    {
        return Platform::tryFrom($this->string('platform')->toString());
    }

    /**
     * Odkaz na PS Store z hledání podle odkazu (R24), který se má připojit.
     */
    public function listingUrl(): ?string
    {
        $url = $this->string('listing_url')->trim()->toString();

        return $url === '' ? null : $url;
    }
}
