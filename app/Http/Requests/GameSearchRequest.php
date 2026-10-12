<?php

/**
 * Hledání hry k přidání.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalog\GameQuery;
use App\Domain\PlayStation\PlayStationUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dotaz `q` — název, Steam App ID nebo odkaz na Steam. Bez dotazu stránka
 * ukáže jen formulář a ITAD se nevolá.
 */
class GameSearchRequest extends FormRequest
{
    /**
     * Hledat smí každý přihlášený (middleware `auth`).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pravidla dotazu.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config('games.catalog.max_query_length')],
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
            'q.max' => __('app.catalog.query_too_long', ['max' => config('games.catalog.max_query_length')]),
        ];
    }

    /**
     * Rozpoznaný dotaz; prázdný = null.
     */
    public function gameQuery(): ?GameQuery
    {
        return GameQuery::parse($this->string('q')->toString());
    }

    /**
     * Odkaz na PS Store nebo stránku hry na playstation.com (R24), jinak null.
     */
    public function playStationUrl(): ?string
    {
        $url = trim($this->string('q')->toString());

        return PlayStationUrl::parse($url) === null ? null : $url;
    }
}
