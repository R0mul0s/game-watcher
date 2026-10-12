<?php

/**
 * Metacritic neodpovídá nebo změnil formát stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * Výpadek, blokace nebo stránka bez strukturovaných dat hry — Metascore se
 * tentokrát nestáhne, dosavadní zůstane (R33).
 */
final class MetacriticUnavailable extends RuntimeException implements SourceUnavailable {}
