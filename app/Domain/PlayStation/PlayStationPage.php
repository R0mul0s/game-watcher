<?php

/**
 * Rozbor stránky PlayStation Store.
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
 * Stránka nese data v JSON skriptech `<script id="env:…">` s cache Apolla
 * (zjištěno 12. 10. 2026, R20). Jeden objekt je v několika skriptech po
 * částech — slučují se. Ceny produktu jsou v objektech `GameCTA:<druh>:…:<ID
 * produktu>-<SKU>:…`:
 *
 * - druh ADD_TO_CART (i PREORDER) se `serviceBranding` NONE — běžná cena,
 * - UPSELL_PS_PLUS_DISCOUNT — cena s PS Plus,
 * - UPSELL_PS_PLUS_GAME_CATALOG — hra je v katalogu PS Plus (zdarma v předplatném),
 *   úroveň v `tierLabel` (TIER_20 Extra, TIER_30 Premium, R35),
 * - UPSELL_PS_PLUS_FREE — měsíční hra PS Plus Essential (zdarma s předplatným, R35).
 *
 * Částky jsou v setinách (`basePriceValue`, `discountedValue`), konec slevy
 * `endTime` v milisekundách. Formát není veřejné API — když se změní, parser
 * vrátí null a nikdy neuloží odhad.
 */
final class PlayStationPage
{
    private const SCRIPT_PATTERN = '~<script id="env:[^"]+" type="application/json">(.*?)</script>~s';

    /** Druhy tlačítek s běžnou cenou — koupě a předobjednávka. */
    private const REGULAR_CTAS = ['ADD_TO_CART', 'PREORDER'];

    private const PLUS_CATALOG_CTA = 'UPSELL_PS_PLUS_GAME_CATALOG';

    private const PLUS_DISCOUNT_CTA = 'UPSELL_PS_PLUS_DISCOUNT';

    /** Měsíční hra PS Plus Essential — zdarma pro předplatitele (R35). */
    private const PLUS_FREE_CTA = 'UPSELL_PS_PLUS_FREE';

    private const BRANDING_NONE = 'NONE';

    /** Obrázky produktu: obal na výšku (2:3) a široký banner (16:9), náhradní v pořadí. */
    private const COVER_ROLES = ['PORTRAIT_BANNER', 'MASTER'];

    private const BANNER_ROLES = ['SIXTEEN_BY_NINE_BANNER', 'GAMEHUB_COVER_ART', 'MASTER'];

    private const IMAGE_TYPE = 'IMAGE';

    /** Údaj o počtu hráčů u jedné konzole (R25). */
    private const LOCAL_PLAYERS_NOTICE = 'NO_OF_PLAYERS';

    /** Údaje o počtu hráčů online (s PS Plus i bez). */
    private const ONLINE_PLAYERS_NOTICES = ['NO_OF_NETWORK_PLAYERS_PS_PLUS', 'NO_OF_NETWORK_PLAYERS'];

    /** Čísla v hodnotě počtu hráčů („2“, „1 - 4“). */
    private const NUMBER_PATTERN = '/\d+/';

    /** Kód češtiny v seznamech jazyků produktu (R47). */
    private const CZECH_CODE = 'cs';

    /** Jazyky rozhraní a titulků — sjednocené přes PS4 i PS5. */
    private const SCREEN_LANGUAGES = 'screenLanguages';

    /** Jazyky dabingu — sjednocené přes PS4 i PS5. */
    private const SPOKEN_LANGUAGES = 'spokenLanguages';

    /** @var array<string, array<string, mixed>> objekty cache podle klíče */
    private array $cache = [];

    private function __construct() {}

    /**
     * Načte a sloučí cache ze všech JSON skriptů stránky.
     */
    public static function fromHtml(string $html): self
    {
        $page = new self;
        preg_match_all(self::SCRIPT_PATTERN, $html, $matches);
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            $cache = is_array($data) && is_array($data['cache'] ?? null) ? $data['cache'] : [];
            foreach ($cache as $key => $value) {
                if (is_string($key) && is_array($value)) {
                    $page->cache[$key] = [...($page->cache[$key] ?? []), ...$value];
                }
            }
        }

        return $page;
    }

    /**
     * Našla se v HTML data obchodu (JSON skripty env:*)? Stránka bez nich není
     * „produkt neexistuje“, ale změněný formát (R29) — ceny se pak nesmí smazat.
     */
    public function hasData(): bool
    {
        return $this->cache !== [];
    }

    /**
     * Výchozí produkt konceptu (stránky hry) — ten, který PS Store ukazuje jako první.
     */
    public function defaultProductId(string $conceptId): ?string
    {
        $ref = $this->cache['Concept:'.$conceptId]['defaultProduct']['__ref'] ?? null;
        if (! is_string($ref) || preg_match('~^Product:('.PlayStationUrl::PRODUCT_ID_PATTERN.')$~', $ref, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * Produkt s cenami; null, když ho stránka nemá.
     */
    public function product(string $productId): ?PlayStationProduct
    {
        $product = $this->cache['Product:'.$productId] ?? null;
        if (! is_array($product) || ! is_string($product['name'] ?? null)) {
            return null;
        }

        // Jen vyjmenované druhy tlačítek — jiné (zkušební verze s PS Plus Premium,
        // UPSELL_PS_PLUS_TRIAL s cenou 0) nejsou cena ani zařazení do katalogu
        $regular = null;
        $plusPrice = null;
        $inPlusCatalog = false;
        $catalogTier = null;
        $plusFree = false;
        foreach ($this->ctas($productId) as $type => $price) {
            $branding = is_array($price['serviceBranding'] ?? null) ? $price['serviceBranding'] : [];

            if ($type === self::PLUS_CATALOG_CTA) {
                $inPlusCatalog = true;
                $catalogTier = PlusTier::fromTierLabel($price['tierLabel'] ?? null);
            } elseif ($type === self::PLUS_FREE_CTA) {
                // Měsíční hra — s PS Plus za 0 (R35)
                $plusFree = true;
                $plusPrice = self::money($price, 'discountedValue');
            } elseif ($type === self::PLUS_DISCOUNT_CTA && ! $plusFree) {
                $plusPrice = self::money($price, 'discountedValue');
            } elseif (in_array($type, self::REGULAR_CTAS, true) && in_array(self::BRANDING_NONE, $branding, true)) {
                $regular ??= $price;
            }
        }

        $endTime = $regular['endTime'] ?? null;
        $price = $regular === null ? null : self::money($regular, 'discountedValue');
        $platforms = is_array($product['platforms'] ?? null) ? $product['platforms'] : [];
        $media = is_array($product['media'] ?? null) ? $product['media'] : [];
        $releaseDate = $product['releaseDate'] ?? null;
        $publisher = $product['publisherName'] ?? null;
        // Hodnocení hráčů (R33); produkt bez hodnocení má průměr 0 a počet 0
        $stars = is_array($product['starRating'] ?? null) ? $product['starRating'] : [];
        $ratingsCount = is_int($stars['totalRatingsCount'] ?? null) ? $stars['totalRatingsCount'] : null;
        $rating = is_int($stars['averageRating'] ?? null) || is_float($stars['averageRating'] ?? null) ? (float) $stars['averageRating'] : null;
        $rated = $rating !== null && $ratingsCount !== null && $ratingsCount > 0;

        return new PlayStationProduct(
            id: $productId,
            name: trim($product['name']),
            platforms: array_values(array_filter($platforms, 'is_string')),
            price: $price,
            basePrice: $regular === null ? null : self::money($regular, 'basePriceValue'),
            saleEndsAt: is_string($endTime) && ctype_digit($endTime) ? CarbonImmutable::createFromTimestampMs((int) $endTime)->utc() : null,
            // Cena s PS Plus, jen když je opravdu nižší než běžná
            plusPrice: $plusPrice !== null && ($price === null || $plusPrice->cents < $price->cents) ? $plusPrice : null,
            inPlusCatalog: $inPlusCatalog,
            catalogTier: $catalogTier,
            plusFree: $plusFree && $plusPrice !== null,
            coverUrl: self::image($media, self::COVER_ROLES, (int) config('games.sources.playstation.cover_width')),
            bannerUrl: self::image($media, self::BANNER_ROLES, (int) config('games.sources.playstation.banner_width')),
            // Datum vydání je půlnoc místního času v UTC (2026-04-29T22:00:00Z = 30. 4.)
            releaseDate: is_string($releaseDate) && strtotime($releaseDate) !== false
                ? CarbonImmutable::parse($releaseDate)->setTimezone((string) config('games.display_timezone'))->toDateString()
                : null,
            publisher: is_string($publisher) && trim($publisher) !== '' ? trim($publisher) : null,
            localPlayers: self::players($product, [self::LOCAL_PLAYERS_NOTICE]),
            onlinePlayers: self::players($product, self::ONLINE_PLAYERS_NOTICES),
            starRating: $rated ? $rating : null,
            starRatingsCount: $rated ? $ratingsCount : null,
            czech: self::czech($product),
        );
    }

    /**
     * Čeština produktu (R47) — v dabingu, nebo jen v rozhraní a titulcích.
     * Produkt s prázdnými seznamy jazyků (bývá u DLC) je neznámý, ne bez češtiny.
     *
     * @param  array<mixed>  $product
     */
    private static function czech(array $product): ?CzechSupport
    {
        $screen = is_array($product[self::SCREEN_LANGUAGES] ?? null) ? $product[self::SCREEN_LANGUAGES] : [];
        $spoken = is_array($product[self::SPOKEN_LANGUAGES] ?? null) ? $product[self::SPOKEN_LANGUAGES] : [];

        return match (true) {
            in_array(self::CZECH_CODE, $spoken, true) => CzechSupport::Voice,
            in_array(self::CZECH_CODE, $screen, true) => CzechSupport::Text,
            $screen === [] && $spoken === [] => null,
            default => CzechSupport::None,
        };
    }

    /**
     * Nejvyšší počet hráčů z upozornění produktu (`compatibilityNoticesByPlatform`
     * — společná i podle platformy); hodnota bývá číslo („2“), u rozsahu se
     * vezme nejvyšší číslo. Null, když produkt údaj nemá.
     *
     * @param  array<mixed>  $product
     * @param  list<string>  $types
     */
    private static function players(array $product, array $types): ?int
    {
        $byPlatform = is_array($product['compatibilityNoticesByPlatform'] ?? null) ? $product['compatibilityNoticesByPlatform'] : [];
        $players = null;
        foreach ($byPlatform as $notices) {
            foreach (is_array($notices) ? $notices : [] as $notice) {
                if (! is_array($notice) || ! in_array($notice['type'] ?? null, $types, true) || ! is_string($notice['value'] ?? null)) {
                    continue;
                }

                preg_match_all(self::NUMBER_PATTERN, $notice['value'], $numbers);
                foreach ($numbers[0] as $number) {
                    $players = max($players ?? 0, (int) $number);
                }
            }
        }

        return $players === 0 ? null : $players;
    }

    /**
     * Adresa obrázku první dostupné role, zmenšená obrázkovým API PlayStationu
     * (`?w=` — originály mají až 3840 px).
     *
     * @param  array<mixed>  $media
     * @param  list<string>  $roles
     */
    private static function image(array $media, array $roles, int $width): ?string
    {
        foreach ($roles as $role) {
            foreach ($media as $item) {
                if (is_array($item) && ($item['type'] ?? null) === self::IMAGE_TYPE && ($item['role'] ?? null) === $role
                    && is_string($item['url'] ?? null) && str_starts_with($item['url'], 'https://')) {
                    return $item['url'].'?w='.$width;
                }
            }
        }

        return null;
    }

    /**
     * Ceny z tlačítek nákupu produktu podle druhu tlačítka.
     *
     * @return array<string, array<string, mixed>>
     */
    private function ctas(string $productId): array
    {
        $ctas = [];
        foreach ($this->cache as $key => $value) {
            if (! str_starts_with($key, 'GameCTA:') || ! str_contains($key, ':'.$productId.'-') || ! is_array($value['price'] ?? null)) {
                continue;
            }
            $type = explode(':', $key)[1];
            $ctas[$type] ??= $value['price'];
        }

        return $ctas;
    }

    /**
     * Částka z objektu ceny (setiny a `currencyCode`).
     *
     * @param  array<string, mixed>  $price
     */
    private static function money(array $price, string $field): ?Money
    {
        $cents = $price[$field] ?? null;
        $currency = $price['currencyCode'] ?? null;

        return is_int($cents) && $cents >= 0 && is_string($currency) ? new Money($cents, $currency) : null;
    }
}
