<?php

/**
 * PlayStation Store neodpovídá nebo stránka nemá očekávaný tvar.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * Výpadek, blokace (Akamai) nebo změna formátu stránky — ceny produktu se
 * tentokrát nestáhnou, dosavadní zůstanou.
 */
final class PlayStationUnavailable extends RuntimeException implements SourceUnavailable {}
