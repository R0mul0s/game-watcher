<?php

/**
 * Proč přišlo upozornění na cenu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Cena pod cílovou cenou, nebo — u hry bez cílové ceny — na historickém
 * minimu (R26); měsíční hra PS Plus Essential pro účet s PS Plus (R35);
 * hra sledovaná před vydáním právě vyšla, nebo je poprvé ve slevě (R41);
 * hra je v Game Passu (R50) nebo v bundlu (R51).
 */
enum AlertReason: string
{
    case Target = 'target';

    case HistoricalLow = 'low';

    case PlusMonthly = 'plus_monthly';

    case Released = 'released';

    case FirstSale = 'first_sale';

    /** Hra je v Game Passu a účet ho má (R50). */
    case GamePass = 'game_pass';

    /** Hra je v aktivním bundlu (R51). */
    case Bundle = 'bundle';

    /**
     * Upozornění na cenu (PriceWatch) — po doručení se pamatuje upozorněná
     * cena; vydání a první sleva (ReleaseWatch, R41), Game Pass a bundly
     * (OfferWatch, R50, R51) mají vlastní stav.
     */
    public function isPriceCondition(): bool
    {
        return ! in_array($this, [self::Released, self::FirstSale, self::GamePass, self::Bundle], true);
    }

    /**
     * Nabídka mimo obchody — Game Pass nebo bundle (OfferWatch, R50, R51).
     */
    public function isOffer(): bool
    {
        return in_array($this, [self::GamePass, self::Bundle], true);
    }

    /**
     * Novinka, ne pokles ceny — předmět e-mailu „Novinky u…“ místo „Zlevnilo…“.
     */
    public function isNews(): bool
    {
        return in_array($this, [self::Released, self::GamePass, self::Bundle], true);
    }
}
