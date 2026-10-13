<?php

/**
 * Obchod s krabicovými hrami neodpovídá.
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
 * Výpadek, blokace (Cloudflare) nebo změna formátu stránky (R52) — cena
 * produktu se tentokrát nestáhne, dosavadní zůstane.
 */
final class RetailUnavailable extends RuntimeException implements SourceUnavailable {}
