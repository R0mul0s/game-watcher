<?php

/**
 * Validace klíče odběru Web Push.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Klíče prohlížeče jsou base64url — veřejný klíč nekomprimovaný bod P-256
 * (65 B), tajemství auth 16 B. Vadný klíč by shodil šifrování zprávy až při
 * rozeslání. Převzato z Počasí.
 */
class PushKey implements ValidationRule
{
    /** Délka nekomprimovaného veřejného klíče P-256 [B]. */
    public const PUBLIC_KEY_BYTES = 65;

    /** Délka tajemství auth [B]. */
    public const AUTH_BYTES = 16;

    /** První bajt nekomprimovaného bodu křivky. */
    private const UNCOMPRESSED_POINT_PREFIX = "\x04";

    /**
     * @param  int  $bytes  Očekávaná délka po dekódování
     */
    public function __construct(private readonly int $bytes) {}

    /**
     * Ověří, že jde o base64url správné délky (a u veřejného klíče bod křivky).
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decoded = is_string($value) && preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) === 1
            ? base64_decode(strtr($value, '-_', '+/'), true)
            : false;

        $valid = is_string($decoded)
            && strlen($decoded) === $this->bytes
            && ($this->bytes !== self::PUBLIC_KEY_BYTES || str_starts_with($decoded, self::UNCOMPRESSED_POINT_PREFIX));

        if (! $valid) {
            $fail('app.validation.push_key')->translate();
        }
    }
}
