<?php

/**
 * Hra, jak ji vrací vyhledávání IsThereAnyDeal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad;

/**
 * Základní údaje hry z `games/search` a `games/lookup` — bez vývojářů, data
 * vydání a hodnocení (ty má až `games/info`, ItadGameInfo).
 */
final readonly class ItadGame
{
    /** Bannery od největšího — vezme se první dostupný. */
    private const BANNER_SIZES = ['banner600', 'banner400', 'banner300', 'banner145'];

    /** Náhled do seznamu výsledků — stačí menší banner. */
    private const THUMBNAIL_SIZES = ['banner300', 'banner400', 'banner145', 'banner600'];

    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $type,
        public bool $mature,
        public ?string $boxartUrl,
        public ?string $bannerUrl,
        public ?string $thumbnailUrl,
    ) {}

    /**
     * Sestaví hru z JSON objektu ITAD; neúplný objekt vrátí jako null.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): ?self
    {
        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;
        $title = $data['title'] ?? null;
        if (! is_string($id) || ! is_string($slug) || ! is_string($title) || $id === '' || $slug === '') {
            return null;
        }

        $assets = is_array($data['assets'] ?? null) ? $data['assets'] : [];

        return new self(
            id: $id,
            slug: $slug,
            // ITAD má u některých názvů mezery navíc („ZAMB! Biomutant Extermination ")
            title: trim($title),
            type: is_string($data['type'] ?? null) ? $data['type'] : 'game',
            mature: ($data['mature'] ?? false) === true,
            boxartUrl: self::firstAsset($assets, ['boxart']),
            bannerUrl: self::firstAsset($assets, self::BANNER_SIZES),
            thumbnailUrl: self::firstAsset($assets, self::THUMBNAIL_SIZES),
        );
    }

    /**
     * Pole pro cache — cache nesmí rozbalovat objekty (cache.serializable_classes).
     *
     * @return array{id: string, slug: string, title: string, type: string, mature: bool, boxartUrl: ?string, bannerUrl: ?string, thumbnailUrl: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'type' => $this->type,
            'mature' => $this->mature,
            'boxartUrl' => $this->boxartUrl,
            'bannerUrl' => $this->bannerUrl,
            'thumbnailUrl' => $this->thumbnailUrl,
        ];
    }

    /**
     * Obnoví hru z pole z toArray().
     *
     * @param  array{id: string, slug: string, title: string, type: string, mature: bool, boxartUrl: ?string, bannerUrl: ?string, thumbnailUrl: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /**
     * První adresa obrázku z vyjmenovaných velikostí, která v odpovědi je.
     *
     * @param  array<mixed>  $assets
     * @param  list<string>  $keys
     */
    private static function firstAsset(array $assets, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($assets[$key] ?? null) && $assets[$key] !== '') {
                return $assets[$key];
            }
        }

        return null;
    }
}
