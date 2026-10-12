<?php

/**
 * Kurzy ČNB nejde stáhnout nebo přečíst.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Currency\Exceptions;

use RuntimeException;

/**
 * Výpadek ČNB nebo změněný formát lístku — převod jede dál s posledním
 * uloženým kurzem (R14).
 */
final class ExchangeRatesUnavailable extends RuntimeException {}
