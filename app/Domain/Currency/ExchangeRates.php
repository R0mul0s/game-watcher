<?php

/**
 * Převod částek mezi měnami kurzem ČNB.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Domain\Pricing\Money;
use App\Models\ExchangeRate;

/**
 * Převádí až při zobrazení (R14) posledním uloženým lístkem — částky v databázi
 * zůstávají v měně zdroje. Lístek se načte jednou za požadavek (scoped
 * v AppServiceProvider). Bez kurzu převod vrátí null a stránka ukáže
 * původní měnu.
 */
final class ExchangeRates
{
    /** Domácí měna lístku ČNB — kurz 1. */
    public const BASE_CURRENCY = 'CZK';

    /** @var array<string, float>|null CZK za 1 jednotku podle kódu měny */
    private ?array $rates = null;

    /**
     * Částka v cílové měně; null, když pro některou z měn chybí kurz.
     */
    public function convert(Money $money, string $currency): ?Money
    {
        if ($money->currency === $currency) {
            return $money;
        }

        $from = $this->rate($money->currency);
        $to = $this->rate($currency);
        if ($from === null || $to === null) {
            return null;
        }

        return new Money((int) round($money->cents * $from / $to), $currency);
    }

    /**
     * Kurz měny (CZK za 1 jednotku) z posledního lístku.
     */
    private function rate(string $currency): ?float
    {
        if ($currency === self::BASE_CURRENCY) {
            return 1.0;
        }

        if ($this->rates === null) {
            $day = ExchangeRate::query()->max('day');
            $this->rates = $day === null ? [] : ExchangeRate::query()
                ->where('day', $day)
                ->pluck('rate', 'currency')
                ->map(fn (mixed $rate): float => (float) $rate)
                ->all();
        }

        return $this->rates[$currency] ?? null;
    }
}
