<?php

/**
 * Produkt PlayStation Store s cenami.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation;

use App\Domain\Pricing\Money;
use App\Enums\CzechSupport;
use App\Enums\PlusTier;
use Carbon\CarbonImmutable;

/**
 * Běžná cena (po slevě, když běží), cena před slevou, cena s PS Plus
 * a zařazení do katalogu PS Plus (R20). Bez běžné ceny se produkt teď
 * nedá koupit (stažený, „Unavailable“).
 */
final readonly class PlayStationProduct
{
    private const PERCENT = 100;

    /** Desetinná místa hvězdiček (store_listings.star_rating decimal(3,2)). */
    private const STAR_DECIMALS = 2;

    /**
     * Části názvu, které katalog ITAD nemá — ochranné známky a označení
     * platformy na konci („Until Dawn™“, „… PS4 & PS5“), R24.
     */
    private const TITLE_NOISE_PATTERNS = [
        '/[™®©]/u',
        '/\s*[-–—:]?\s*\(?PS[45](?:\s*(?:&|\/|,|a|and)\s*PS[45])?\)?\s*$/iu',
    ];

    /**
     * @param  list<string>  $platforms
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $platforms,
        public ?Money $price,
        public ?Money $basePrice,
        public ?CarbonImmutable $saleEndsAt,
        public ?Money $plusPrice,
        public bool $inPlusCatalog,
        // Úroveň katalogu PS Plus (Extra / Premium) a měsíční hra Essential (R35)
        public ?PlusTier $catalogTier = null,
        // Kdy hra z katalogu PS Plus odchází (R49); null = zůstává nebo v něm není
        public ?CarbonImmutable $catalogLeavesAt = null,
        public bool $plusFree = false,
        public ?string $coverUrl = null,
        public ?string $bannerUrl = null,
        public ?string $releaseDate = null,
        public ?string $publisher = null,
        public ?int $localPlayers = null,
        public ?int $onlinePlayers = null,
        // Hodnocení hráčů na PS Store (R33) — průměr hvězdiček 0–5 a počet hodnocení
        public ?float $starRating = null,
        public ?int $starRatingsCount = null,
        // Čeština (R47); null = produkt jazyky neuvádí
        public ?CzechSupport $czech = null,
    ) {}

    /**
     * Údaje produktu, které se ukládají k propojení (store_listings) při
     * připojení i každé obnově ceny — počet hráčů (R25), hodnocení hráčů (R33)
     * a čeština (R47).
     *
     * @return array{local_players: int|null, online_players: int|null, star_rating: string|null, star_ratings_count: int|null, czech: CzechSupport|null}
     */
    public function listingDetails(): array
    {
        return [
            'local_players' => $this->localPlayers,
            'online_players' => $this->onlinePlayers,
            'star_rating' => $this->starRating === null ? null : number_format($this->starRating, self::STAR_DECIMALS, '.', ''),
            'star_ratings_count' => $this->starRatingsCount,
            'czech' => $this->czech,
        ];
    }

    /**
     * Název pro hledání hry v katalogu ITAD — bez ochranných známek
     * a označení platformy.
     */
    public function searchTitle(): string
    {
        $title = preg_replace(self::TITLE_NOISE_PATTERNS, '', $this->name) ?? $this->name;

        return trim($title) === '' ? $this->name : trim($title);
    }

    /**
     * Sleva běžné ceny proti ceně před slevou v procentech (0 bez slevy).
     */
    public function cut(): int
    {
        if ($this->price === null || $this->basePrice === null || $this->basePrice->cents <= 0 || $this->price->cents >= $this->basePrice->cents) {
            return 0;
        }

        return (int) round(($this->basePrice->cents - $this->price->cents) * self::PERCENT / $this->basePrice->cents);
    }
}
