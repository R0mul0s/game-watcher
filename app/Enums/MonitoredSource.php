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

    /** Obnova údajů her z ITAD (R34) — doplňkové údaje */
    case GameInfo = 'game_info';

    /** Žánry a režimy ze Steamu (R25) — doplňkové údaje */
    case Steam = 'steam';

    /** Metascore z Metacritic pro hry mimo ITAD (R33) — doplňkové údaje */
    case Metacritic = 'metacritic';

    /** Délka hraní z HowLongToBeat (R44) — doplňkové údaje */
    case Hltb = 'hltb';

    /** Nadcházející hry z IGDB (R46) — doplňkové údaje */
    case Igdb = 'igdb';

    /** Doručení upozornění e-mailem a na zařízení (R26) */
    case Alerts = 'alerts';

    /** Týdenní souhrn slev e-mailem (R36) */
    case WeeklyDigest = 'weekly_digest';

    /**
     * Řádek stavu pro zdroj cen.
     */
    public static function fromPriceSource(PriceSource $source): self
    {
        return self::from($source->value);
    }

    /**
     * Hlásí výpadek monitoringu (/health/zdroje)? Obnova údajů, Steam, Metacritic,
     * HowLongToBeat a IGDB ne — údaje her, žánry, režimy, hodnocení, délka hraní
     * a nadcházející hry jsou doplňkové a jejich výpadek neoznačí ani běh cronu
     * jako chybný (R25, R33, R34, R44, R46).
     */
    public function isCritical(): bool
    {
        return ! in_array($this, [self::GameInfo, self::Steam, self::Metacritic, self::Hltb, self::Igdb], true);
    }
}
