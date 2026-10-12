<?php

/**
 * Hra ze stránky Metacritic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic;

/**
 * Údaje ze strukturovaných dat stránky (schema.org VideoGame v JSON-LD, R33) —
 * stabilnější než vykreslený obsah stránky. Metascore (`aggregateRating`) chybí
 * u hry, kterou kritici ještě nehodnotili.
 */
final readonly class MetacriticGame
{
    private const LD_JSON_PATTERN = '~<script type="application/ld\+json"[^>]*>(.*?)</script>~s';

    private const GAME_TYPE = 'VideoGame';

    private const MIN_SCORE = 0;

    private const MAX_SCORE = 100;

    /**
     * @param  list<string>  $platforms
     */
    public function __construct(
        public string $name,
        public ?int $score,
        public ?int $reviews,
        public array $platforms,
    ) {}

    /**
     * Hra ze stránky; null, když stránka strukturovaná data hry nemá (změna formátu).
     */
    public static function fromHtml(string $html): ?self
    {
        preg_match_all(self::LD_JSON_PATTERN, $html, $matches);
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (! is_array($data) || ($data['@type'] ?? null) !== self::GAME_TYPE || ! is_string($data['name'] ?? null)) {
                continue;
            }

            $rating = is_array($data['aggregateRating'] ?? null) ? $data['aggregateRating'] : [];
            $score = $rating['ratingValue'] ?? null;
            $reviews = $rating['reviewCount'] ?? null;
            $platforms = is_array($data['gamePlatform'] ?? null) ? $data['gamePlatform'] : [];

            return new self(
                name: trim($data['name']),
                score: is_int($score) && $score >= self::MIN_SCORE && $score <= self::MAX_SCORE ? $score : null,
                reviews: is_int($reviews) ? $reviews : null,
                platforms: array_values(array_filter($platforms, 'is_string')),
            );
        }

        return null;
    }
}
