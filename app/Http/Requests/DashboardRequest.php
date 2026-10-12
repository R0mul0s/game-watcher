<?php

/**
 * Filtry přehledu sledovaných her v adrese.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Pricing\Money;
use App\Domain\Watchlist\WatchlistFilters;
use App\Enums\Platform;
use App\Enums\PlayerFilter;
use App\Enums\WatchlistSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Neplatná hodnota filtru (ručně upravená adresa) se nehlásí chybou — filtr
 * se jen nepoužije, přehled se vždy zobrazí (R21).
 */
class DashboardRequest extends FormRequest
{
    private const MAX_QUERY_LENGTH = 100;

    /** Nejdelší název žánru (Steam má krátké názvy, „Masivně multiplayerové“). */
    private const MAX_GENRE_LENGTH = 64;

    private const MAX_PERCENT = 100;

    /** Nejvyšší cena ve filtru (v měně zobrazení) — jen hrubá mez proti nesmyslům. */
    private const MAX_PRICE = 1000000;

    /** Druhy her, podle kterých jde filtrovat. */
    private const TYPES = ['game', 'dlc'];

    /**
     * Přehled vidí každý přihlášený (middleware `auth`).
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
    public function filters(): WatchlistFilters
    {
        $valid = validator($this->query(), [
            'q' => ['string', 'max:'.self::MAX_QUERY_LENGTH],
            'platform' => [Rule::enum(Platform::class)],
            'genres' => ['array'],
            'genres.*' => ['string', 'max:'.self::MAX_GENRE_LENGTH],
            'players' => [Rule::enum(PlayerFilter::class)],
            'deals' => ['boolean'],
            'max_price' => ['numeric', 'min:0', 'max:'.self::MAX_PRICE],
            'min_cut' => ['integer', 'min:1', 'max:'.self::MAX_PERCENT],
            'type' => [Rule::in(self::TYPES)],
            'sort' => [Rule::enum(WatchlistSort::class)],
            'plus' => ['boolean'],
        ]);
        $failed = $valid->errors()->keys();
        $value = fn (string $key): mixed => in_array($key, $failed, true) || collect($failed)->contains(fn (string $error): bool => str_starts_with($error, $key.'.'))
            ? null
            : $this->query($key);

        $genres = $value('genres');
        $maxPrice = $value('max_price');
        $minCut = $value('min_cut');
        $type = $value('type');

        return new WatchlistFilters(
            query: trim((string) ($value('q') ?? '')),
            platform: Platform::tryFrom((string) $value('platform')),
            genres: is_array($genres) && $genres !== [] ? array_values(array_map('strval', $genres)) : null,
            players: PlayerFilter::tryFrom((string) $value('players')),
            dealsOnly: filter_var($value('deals'), FILTER_VALIDATE_BOOLEAN),
            maxPriceCents: is_numeric($maxPrice) ? (int) round((float) $maxPrice * Money::SUBUNITS) : null,
            minCut: is_numeric($minCut) ? (int) $minCut : null,
            type: is_string($type) ? $type : null,
            sort: WatchlistSort::tryFrom((string) $value('sort')) ?? WatchlistSort::Added,
            withPlus: filter_var($value('plus'), FILTER_VALIDATE_BOOLEAN),
        );
    }
}
