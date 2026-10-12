<?php

/**
 * Kurzovní lístek ČNB (kurzy devizového trhu).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Domain\Currency\Exceptions\ExchangeRatesUnavailable;
use Carbon\CarbonImmutable;

/**
 * Textový formát ČNB (R14):
 *
 *     09.10.2026 #195
 *     země|měna|množství|kód|kurz
 *     EMU|euro|1|EUR|24,365
 *     Filipíny|peso|100|PHP|34,615
 *
 * Kurz platí pro `množství` jednotek — ukládá se přepočtený na jednu jednotku.
 */
final readonly class CnbRateSheet
{
    /** První řádek: datum vyhlášení a pořadové číslo. */
    private const HEADER_PATTERN = '/^(\d{2}\.\d{2}\.\d{4})\s+#\d+/';

    private const DATE_FORMAT = 'd.m.Y';

    private const COLUMN_SEPARATOR = '|';

    /** Sloupce řádku kurzu: země, měna, množství, kód, kurz. */
    private const COLUMN_COUNT = 5;

    /** Desetinných míst kurzu za jednotku (sloupec decimal(14,6)). */
    private const RATE_DECIMALS = 6;

    /**
     * @param  array<string, string>  $rates  CZK za 1 jednotku podle kódu měny, jako desetinný řetězec
     */
    public function __construct(
        public CarbonImmutable $day,
        public array $rates,
    ) {}

    /**
     * Rozebere lístek; nečekaný tvar = výjimka (formát se mohl změnit).
     *
     * @throws ExchangeRatesUnavailable
     */
    public static function parse(string $text): self
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];
        if (preg_match(self::HEADER_PATTERN, $lines[0] ?? '', $match) !== 1) {
            throw new ExchangeRatesUnavailable('Kurzovní lístek ČNB nemá očekávanou hlavičku.');
        }

        $rates = [];
        // Řádek 0 je datum, 1 názvy sloupců
        foreach (array_slice($lines, 2) as $line) {
            $columns = explode(self::COLUMN_SEPARATOR, trim($line));
            if (count($columns) !== self::COLUMN_COUNT) {
                continue;
            }
            [, , $amount, $code, $rate] = $columns;
            $rate = (float) str_replace(',', '.', $rate);
            if ((int) $amount > 0 && $rate > 0 && preg_match('/^[A-Z]{3}$/', $code) === 1) {
                $rates[$code] = number_format($rate / (int) $amount, self::RATE_DECIMALS, '.', '');
            }
        }

        if ($rates === []) {
            throw new ExchangeRatesUnavailable('Kurzovní lístek ČNB neobsahuje žádný kurz.');
        }

        $day = CarbonImmutable::createFromFormat(self::DATE_FORMAT, $match[1], 'UTC')
            ?? throw new ExchangeRatesUnavailable("Kurzovní lístek ČNB má neplatné datum „{$match[1]}“.");

        return new self($day->startOfDay(), $rates);
    }
}
