<?php

/**
 * Filtry stránky Objevit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Discover;

use App\Enums\DiscoverSort;

/**
 * Stav je v adrese (`?tags[]=RPG&sort=cut`) jako u přehledu (R21); ověřuje
 * ho DiscoverRequest. Každá kombinace filtrů je jeden požadavek na ITAD
 * (v cache), proto jen pár voleb (R43).
 */
final readonly class DiscoverFilters
{
    /** Druh hry v ITAD `deals/v2` — jen hry, bez DLC a balíčků. */
    private const ITAD_TYPE_GAME = 1;

    /** Značka ITAD „historické minimum“ (zahrnuje i nové minimum). */
    private const ITAD_FLAG_HISTORICAL_LOW = 'H';

    /**
     * @param  list<string>  $tags  štítky ze Steamu (stačí jeden z nich); prázdné = všechny hry
     */
    public function __construct(
        public array $tags = [],
        public DiscoverSort $sort = DiscoverSort::DEFAULT,
        public bool $lowsOnly = true,
    ) {}

    /**
     * Filtr pro ITAD — jen hry, volitelně na historickém minimu a se štítky.
     *
     * @return array<string, mixed>
     */
    public function itadFilter(): array
    {
        return array_filter([
            'type' => [self::ITAD_TYPE_GAME],
            'flag' => $this->lowsOnly ? self::ITAD_FLAG_HISTORICAL_LOW : null,
            'tagsUnion' => $this->tags === [] ? null : $this->tags,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Klíč cache — stejné filtry v jiném pořadí štítků jsou týž dotaz.
     */
    public function cacheKey(): string
    {
        $tags = $this->tags;
        sort($tags);

        return hash('sha256', (string) json_encode([$tags, $this->sort->value, $this->lowsOnly]));
    }

    /**
     * Stav pro stránku.
     *
     * @return array{tags: list<string>, sort: string, lows: bool}
     */
    public function toArray(): array
    {
        return ['tags' => $this->tags, 'sort' => $this->sort->value, 'lows' => $this->lowsOnly];
    }
}
