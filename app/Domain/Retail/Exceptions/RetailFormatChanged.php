<?php

/**
 * Stránka obchodu nemá produkt ve schema.org.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * V cronu výpadek — obchod změnil web, cena se nesmaže (R29). Při připojování
 * odkaz nevede na produkt (kategorie, výpis) — chyba formuláře (R52).
 */
final class RetailFormatChanged extends RuntimeException implements SourceUnavailable {}
