<?php

/**
 * Produkt PlayStation Store podle odkazu od uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Actions;

use App\Domain\PlayStation\Exceptions\PlayStationProductNotFound;
use App\Domain\PlayStation\Exceptions\PlayStationUnavailable;
use App\Domain\PlayStation\PlayStationClient;
use App\Domain\PlayStation\PlayStationProduct;
use App\Domain\PlayStation\PlayStationUrl;

/**
 * Odkaz na produkt, koncept v PS Store nebo stránku hry na www.playstation.com
 * (R20, R22). Z konceptu se vezme jeho výchozí produkt, ze stránky hry první
 * odkaz do obchodu (standardní edice). Hostitele ověřuje jen PlayStationUrl (SSRF).
 * Používá ho připojení k hře i hledání hry podle odkazu (R24).
 */
final class FindPlayStationProduct
{
    public function __construct(private readonly PlayStationClient $client) {}

    /**
     * Produkt z odkazu.
     *
     * @throws PlayStationProductNotFound
     * @throws PlayStationUnavailable
     */
    public function handle(string $url): PlayStationProduct
    {
        $parsed = PlayStationUrl::parse($url) ?? throw new PlayStationProductNotFound($url);

        $productId = match ($parsed->kind) {
            PlayStationUrl::KIND_PRODUCT => $parsed->id,
            PlayStationUrl::KIND_CONCEPT => $this->client->conceptDefaultProductId($parsed->id),
            default => $this->client->gamePageProductId($parsed->id),
        } ?? throw new PlayStationProductNotFound($url);

        return $this->client->product($productId) ?? throw new PlayStationProductNotFound($url);
    }
}
