<?php

/**
 * Podle jaké ceny přehled řadí a filtruje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Volba „Cena“ ve filtrech přehledu (R30) — řídí řazení od nejlevnější,
 * „cena do“ a zvýrazněnou cenu na kartě. Sleva se počítá vždy z oficiálních
 * obchodů: obchody s klíči (gg.deals) běžnou cenu ani slevu neuvádějí.
 */
enum PriceBasis: string
{
    /** Oficiální obchody a PlayStation Store — výchozí */
    case Official = 'official';

    /** Nejlevnější nabídka včetně obchodů s klíči — jako upozornění (R26) */
    case Cheapest = 'cheapest';

    /** Jen nejnižší cena obchodů s klíči */
    case Keyshops = 'keyshops';
}
