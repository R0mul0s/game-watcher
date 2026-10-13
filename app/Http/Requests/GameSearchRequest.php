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
use App\Domain\Catalog\SearchFilters;
use App\Domain\PlayStation\PlayStationUrl;
use App\Enums\Platform;
use App\Enums\SearchSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dotaz `q` — název, Steam App ID, odkaz na Steam, PS Store nebo ITAD. Bez
 * dotazu stránka ukáže jen formulář a ITAD se nevolá. Filtry výsledků (druh,
 * platforma, řazení, víc výsledků) jsou další parametry adresy (R30). Odkaz
 * sdílený z jiné aplikace v telefonu přijde v `url` nebo `text` (R37).
 */
class GameSearchRequest extends FormRequest
{
    /** Nejdelší sdílený text nebo odkaz z jiné aplikace. */
    private const MAX_SHARED_LENGTH = 1000;

    /** První odkaz ve sdíleném textu („Saros – PS Store https://store.playstation.com/…“). */
    private const URL_IN_TEXT_PATTERN = '~https?://\S+~';

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
            // Sdílení z jiné aplikace v telefonu (share_target v manifestu, R37)
            'url' => ['nullable', 'string', 'max:'.self::MAX_SHARED_LENGTH],
            'text' => ['nullable', 'string', 'max:'.self::MAX_SHARED_LENGTH],
            'title' => ['nullable', 'string', 'max:'.self::MAX_SHARED_LENGTH],
        ];
    }

    /**
     * Platné filtry výsledků z adresy (R30); neplatné hodnoty vynechá — jako
     * filtry přehledu, chyby se nevrací.
     */
    public function searchFilters(): SearchFilters
    {
        $valid = validator($this->query(), [
            'type' => [Rule::in(SearchFilters::TYPES)],
            'platform' => [Rule::enum(Platform::class)],
            'sort' => [Rule::enum(SearchSort::class)],
            'more' => ['boolean'],
        ])->valid();

        return new SearchFilters(
            type: is_string($valid['type'] ?? null) ? $valid['type'] : null,
            platform: Platform::tryFrom((string) ($valid['platform'] ?? '')),
            sort: SearchSort::tryFrom((string) ($valid['sort'] ?? '')) ?? SearchSort::Relevance,
            more: filter_var($valid['more'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
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
        return GameQuery::parse($this->queryText());
    }

    /**
     * Text hledání — `q`, a když chybí, odkaz sdílený z jiné aplikace (R37):
     * `url`, jinak první odkaz v `text` (obchody posílají odkaz v textu s názvem),
     * jinak `text` celý.
     */
    public function queryText(): string
    {
        $query = trim($this->string('q')->toString());
        if ($query !== '') {
            return $query;
        }

        $url = trim($this->string('url')->toString());
        if ($url !== '') {
            return $url;
        }

        $text = trim($this->string('text')->toString());

        return preg_match(self::URL_IN_TEXT_PATTERN, $text, $match) === 1 ? $match[0] : $text;
    }

    /**
     * Odkaz na PS Store nebo stránku hry na playstation.com (R24), jinak null.
     */
    public function playStationUrl(): ?string
    {
        $url = $this->queryText();

        return PlayStationUrl::parse($url) === null ? null : $url;
    }
}
