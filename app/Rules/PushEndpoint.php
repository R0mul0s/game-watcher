<?php

/**
 * Validace adresy odběru Web Push.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Adresa odběru musí být HTTPS u známé push služby (games.push.allowed_hosts,
 * R13) — server na ni posílá požadavek, libovolná adresa by z něj udělala
 * nástroj na útoky na jiné servery (SSRF). Převzato z Počasí.
 */
class PushEndpoint implements ValidationRule
{
    /** Nejdelší přijatá adresa (FCM i Apple mají kolem 200 znaků). */
    public const MAX_LENGTH = 1000;

    /**
     * Ověří schéma a hostitele adresy.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = is_string($value) && str_starts_with($value, 'https://') ? parse_url($value, PHP_URL_HOST) : null;

        /** @var list<string> $allowedHosts */
        $allowedHosts = config('games.push.allowed_hosts');
        if (! is_string($host) || ! Str::is($allowedHosts, $host)) {
            $fail('app.validation.push_endpoint')->translate();
        }
    }
}
