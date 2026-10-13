<?php

/**
 * Odkaz na produkt obchodu s krabicovými hrami od uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail;

use App\Enums\PriceSource;

/**
 * Jediné místo, kde se ověří hostitel vloženého odkazu (R52, SSRF) — server
 * pak volá jen adresu složenou odsud: Xzone (www.xzone.cz/<produkt>) a Heureka
 * (<kategorie>.heureka.cz/<produkt>/). Dotaz a kotva se zahodí.
 */
final readonly class RetailUrl
{
    /** Cesta produktu Xzone: /ghost-of-yotei-complete-edition-ps5 (jeden segment). */
    private const XZONE_PATH_PATTERN = '~^/([a-z0-9][a-z0-9-]{0,199})$~';

    /** Poddoména kategorie Heureky: hry-na-ps5. */
    private const HEUREKA_SUBDOMAIN_PATTERN = '~^[a-z0-9][a-z0-9-]{0,62}$~';

    /** Cesta produktu Heureky: /astro-bot/ (jeden segment). */
    private const HEUREKA_PATH_PATTERN = '~^/([a-z0-9][a-z0-9-]{0,199})/?$~';

    /** Poddomény Heureky, které nejsou kategorií produktů. */
    private const HEUREKA_RESERVED_SUBDOMAINS = ['www', 'obchody', 'blog', 'napoveda'];

    private function __construct(
        public PriceSource $source,
        public string $url,
    ) {}

    /**
     * Rozpozná odkaz; jiný hostitel nebo tvar = null.
     */
    public static function parse(string $input): ?self
    {
        $input = trim($input);
        if (! preg_match('~^https?://~i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = strtolower($parts['path'] ?? '');

        $xzoneHost = (string) config('games.sources.retail.xzone_host');
        // Odkaz bez www (xzone.cz/…) vede na stejný obchod — volá se vždy hostitel z konfigurace
        if ($host === $xzoneHost || 'www.'.$host === $xzoneHost) {
            return preg_match(self::XZONE_PATH_PATTERN, rtrim($path, '/'), $match) === 1
                ? new self(PriceSource::Xzone, 'https://'.$xzoneHost.'/'.$match[1])
                : null;
        }

        $suffix = (string) config('games.sources.retail.heureka_host_suffix');
        if (str_ends_with($host, $suffix)) {
            $subdomain = substr($host, 0, -strlen($suffix));
            if (preg_match(self::HEUREKA_SUBDOMAIN_PATTERN, $subdomain) !== 1 || in_array($subdomain, self::HEUREKA_RESERVED_SUBDOMAINS, true)) {
                return null;
            }

            return preg_match(self::HEUREKA_PATH_PATTERN, $path, $match) === 1
                ? new self(PriceSource::Heureka, 'https://'.$host.'/'.$match[1].'/')
                : null;
        }

        return null;
    }

    /**
     * ID produktu pro propojení (store_listings.external_id) — otisk adresy,
     * adresy Heureky bývají delší než sloupec.
     */
    public function externalId(): string
    {
        return md5($this->url);
    }
}
