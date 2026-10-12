<?php

/**
 * Hlídané kroky a zdroje cron URL.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Řádky stavu zdrojů (R29) — v pořadí, v jakém je cron volá. Zdroje cen mají
 * stejnou hodnotu jako PriceSource.
 */
enum MonitoredSource: string
{
    /** Běh cron URL jako celek — pozná, že ho hosting přestal volat */
    case Cron = 'cron';

    /** Kurzovní lístek ČNB (R14) */
    case ExchangeRates = 'exchange_rates';

    case Itad = 'itad';

    case GgDeals = 'ggdeals';

    case PlayStation = 'playstation';

    /** Žánry a režimy ze Steamu (R25) — doplňkové údaje */
    case Steam = 'steam';

    /** Metascore z Metacritic pro hry mimo ITAD (R33) — doplňkové údaje */
    case Metacritic = 'metacritic';

    /** Doručení upozornění e-mailem a na zařízení (R26) */
    case Alerts = 'alerts';

    /**
     * Řádek stavu pro zdroj cen.
     */
    public static function fromPriceSource(PriceSource $source): self
    {
        return self::from($source->value);
    }

    /**
     * Hlásí výpadek monitoringu (/health/zdroje)? Steam a Metacritic ne — žánry,
     * režimy a hodnocení jsou doplňkové a jejich výpadek neoznačí ani běh cronu
     * jako chybný (R25, R33).
     */
    public function isCritical(): bool
    {
        return $this !== self::Steam && $this !== self::Metacritic;
    }
}
