<?php

/**
 * Podrobné údaje hry z IsThereAnyDeal (`games/info`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad;

/**
 * Model hry, ze kterého vzniká záznam v katalogu (R10) — vedle základních
 * údajů Steam App ID, datum vydání, vývojáři, vydavatelé, štítky a hodnocení.
 */
final readonly class ItadGameInfo
{
    /**
     * @param  list<string>  $developers
     * @param  list<string>  $publishers
     * @param  list<string>  $tags
     * @param  list<array{source: string, score: int, count: int|null, url: string|null}>  $reviews
     */
    public function __construct(
        public ItadGame $game,
        public ?int $steamAppId,
        public ?string $releaseDate,
        public array $developers,
        public array $publishers,
        public array $tags,
        public array $reviews,
        public ?string $url,
    ) {}

    /**
     * Sestaví údaje z JSON odpovědi ITAD; bez základních údajů hry null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $game = ItadGame::fromApi($data);
        if ($game === null) {
            return null;
        }

        $appId = $data['appid'] ?? null;
        $releaseDate = $data['releaseDate'] ?? null;
        $urls = is_array($data['urls'] ?? null) ? $data['urls'] : [];

        return new self(
            game: $game,
            steamAppId: is_int($appId) && $appId > 0 ? $appId : null,
            releaseDate: is_string($releaseDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $releaseDate) === 1 ? $releaseDate : null,
            developers: self::names($data['developers'] ?? null),
            publishers: self::names($data['publishers'] ?? null),
            tags: self::strings($data['tags'] ?? null),
            reviews: self::reviews($data['reviews'] ?? null),
            url: is_string($urls['game'] ?? null) ? $urls['game'] : null,
        );
    }

    /**
     * Názvy z pole objektů `{id, name}` (vývojáři, vydavatelé).
     *
     * @return list<string>
     */
    private static function names(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return self::strings(array_map(fn (mixed $item): mixed => is_array($item) ? ($item['name'] ?? null) : null, $items));
    }

    /**
     * Neprázdné řetězce z pole, ostatní hodnoty zahodí.
     *
     * @return list<string>
     */
    private static function strings(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    /**
     * Hodnocení ze Steamu, Metacritic a OpenCritic — jen úplné položky.
     *
     * @return list<array{source: string, score: int, count: int|null, url: string|null}>
     */
    private static function reviews(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $reviews = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['source'] ?? null) || ! is_int($item['score'] ?? null)) {
                continue;
            }
            $reviews[] = [
                'source' => $item['source'],
                'score' => $item['score'],
                'count' => is_int($item['count'] ?? null) ? $item['count'] : null,
                'url' => is_string($item['url'] ?? null) ? $item['url'] : null,
            ];
        }

        return $reviews;
    }
}
