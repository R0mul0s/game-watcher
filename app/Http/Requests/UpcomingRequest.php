<?php

/**
 * Filtry záložky Nadcházející v adrese.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Discover\UpcomingFilters;
use App\Enums\Platform;
use App\Enums\UpcomingSort;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Neplatná hodnota (ručně upravená adresa) se nehlásí — filtr se jen
 * nepoužije, stránka se vždy zobrazí (jako Objevit, R43).
 */
class UpcomingRequest extends FormRequest
{
    /** Nejdelší žánr (IGDB má nejdelší „Hack and slash/Beat 'em up“). */
    private const MAX_GENRE_LENGTH = 64;

    /** Nejvýš tolik vybraných žánrů. */
    private const MAX_GENRES = 5;

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
     * Platné filtry z adresy; neplatné hodnoty vynechá.
     */
    public function filters(): UpcomingFilters
    {
        $genres = $this->query('genres');
        $genres = is_array($genres) ? array_values(array_unique(array_filter(
            $genres,
            fn (mixed $genre): bool => is_string($genre) && $genre !== '' && mb_strlen($genre) <= self::MAX_GENRE_LENGTH,
        ))) : [];

        return new UpcomingFilters(
            platform: Platform::tryFrom((string) $this->string('platform')),
            genres: array_slice($genres, 0, self::MAX_GENRES),
            coop: filter_var($this->query('coop'), FILTER_VALIDATE_BOOLEAN),
            sort: UpcomingSort::tryFrom((string) $this->string('sort')) ?? UpcomingSort::DEFAULT,
        );
    }
}
