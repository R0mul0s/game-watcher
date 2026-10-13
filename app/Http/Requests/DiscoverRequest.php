<?php

/**
 * Filtry stránky Objevit v adrese.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Discover\DiscoverFilters;
use App\Enums\DiscoverSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Neplatná hodnota (ručně upravená adresa) se nehlásí — filtr se jen
 * nepoužije, stránka se vždy zobrazí (jako přehled, R21).
 */
class DiscoverRequest extends FormRequest
{
    /** Nejdelší štítek (Steam má krátké, „Massively Multiplayer“). */
    private const MAX_TAG_LENGTH = 64;

    /**
     * Stránku vidí každý přihlášený (middleware `auth`).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pravidla — validace jen pro filters(), chyby se nevrací (viz třída).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Platné filtry z adresy; neplatné hodnoty vynechá, štítků nejvýš discover.max_tags.
     */
    public function filters(): DiscoverFilters
    {
        $tags = $this->query('tags');
        $tags = is_array($tags) ? array_values(array_unique(array_filter(
            $tags,
            fn (mixed $tag): bool => is_string($tag) && $tag !== '' && mb_strlen($tag) <= self::MAX_TAG_LENGTH,
        ))) : [];

        $valid = validator($this->query(), [
            'sort' => [Rule::enum(DiscoverSort::class)],
            'lows' => ['boolean'],
        ]);
        $failed = $valid->errors()->keys();

        return new DiscoverFilters(
            tags: array_slice($tags, 0, (int) config('games.discover.max_tags')),
            sort: in_array('sort', $failed, true) ? DiscoverSort::DEFAULT : (DiscoverSort::tryFrom((string) $this->query('sort')) ?? DiscoverSort::DEFAULT),
            lowsOnly: in_array('lows', $failed, true) || $this->query('lows') === null || filter_var($this->query('lows'), FILTER_VALIDATE_BOOLEAN),
        );
    }
}
