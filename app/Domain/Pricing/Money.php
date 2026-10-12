<?php

/**
 * Peněžní částka v jedné měně.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

use InvalidArgumentException;

/**
 * Částka v setinách měny (centy, haléře) — celé číslo, žádný float
 * (docs/CODING_GUIDELINES.md, Peníze a měny). Do databáze jde jako
 * decimal(10,2), ven jako desetinné číslo s měnou.
 */
final readonly class Money
{
    /** Setin v jedné jednotce měny. */
    public const SUBUNITS = 100;

    /** Desetinných míst v databázi (decimal(10,2)). */
    private const DECIMALS = 2;

    /** Desetinné číslo s tečkou: „49.18“, „5.4“, „-3“, „1419.00“. */
    private const DECIMAL_PATTERN = '/^(-?)(\d+)(?:\.(\d+))?$/';

    public function __construct(
        public int $cents,
        public string $currency,
    ) {}

    /**
     * Částka z desetinného zápisu (API, sloupec decimal) — bez převodu přes float.
     *
     * @throws InvalidArgumentException
     */
    public static function fromDecimal(string $amount, string $currency): self
    {
        if (preg_match(self::DECIMAL_PATTERN, trim($amount), $match) !== 1) {
            throw new InvalidArgumentException("Neplatná částka „{$amount}“.");
        }

        $fraction = substr(str_pad($match[3] ?? '', self::DECIMALS, '0'), 0, self::DECIMALS);
        $cents = (int) $match[2] * self::SUBUNITS + (int) $fraction;

        return new self($match[1] === '-' ? -$cents : $cents, strtoupper($currency));
    }

    /**
     * Desetinný zápis pro sloupec decimal(10,2): „49.18“.
     */
    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $cents = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, self::SUBUNITS), $cents % self::SUBUNITS);
    }

    /**
     * Částka a měna pro stránku — číslo formátuje prohlížeč (Intl).
     *
     * @return array{amount: float, currency: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->cents / self::SUBUNITS, 'currency' => $this->currency];
    }
}
