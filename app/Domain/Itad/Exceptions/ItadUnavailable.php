<?php

/**
 * IsThereAnyDeal API neodpovídá nebo vrátilo nečekanou odpověď.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Itad\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * Výpadek služby, neplatný klíč, překročený limit nebo změněný tvar dat —
 * pro uživatele vždy stejné „katalog teď nejde načíst“, podrobnosti v logu.
 */
final class ItadUnavailable extends RuntimeException implements SourceUnavailable {}
