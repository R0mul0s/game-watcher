<?php

/**
 * Cena jako text pro e-mail a upozornění na telefon.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Na stránce formátuje ceny prohlížeč (resources/js/lib/format.js); tohle je
 * totéž na serveru pro texty, které stránka nezobrazuje — česky, koruny bez
 * haléřů. Bez Intl: data ICU pro češtinu na serveru chybět mohou (v Dockeru
 * vrací „CZK 132“), a měny jsou jen dvě (R14). Celočíselně, bez floatu.
 */
final class MoneyFormatter
{
    /** Desetinná místa podle měny — ostatní měny dvě. */
    private const FRACTION_DIGITS = ['CZK' => 0];

    private const DEFAULT_FRACTION_DIGITS = 2;

    /** Značka měny za částkou; neznámá měna jejím kódem. */
    private const SYMBOLS = ['CZK' => 'Kč', 'EUR' => '€'];

    /** Oddělovač tisíců a mezera před měnou — nezlomitelná mezera jako v Intl. */
    private const SPACE = "\u{a0}";

    private const DECIMAL_SEPARATOR = ',';

    /**
     * „1 198 Kč“, „49,18 €“.
     */
    public function format(Money $money): string
    {
        $digits = self::FRACTION_DIGITS[$money->currency] ?? self::DEFAULT_FRACTION_DIGITS;
        $cents = abs($money->cents);

        $text = $digits === 0
            // Zaokrouhlení na celé koruny (půl nahoru)
            ? number_format(intdiv($cents + intdiv(Money::SUBUNITS, 2), Money::SUBUNITS), 0, '', self::SPACE)
            : number_format(intdiv($cents, Money::SUBUNITS), 0, '', self::SPACE)
                .self::DECIMAL_SEPARATOR
                .str_pad((string) ($cents % Money::SUBUNITS), $digits, '0', STR_PAD_LEFT);

        return ($money->cents < 0 ? '−' : '').$text.self::SPACE.(self::SYMBOLS[$money->currency] ?? $money->currency);
    }
}
