<?php

/**
 * HowLongToBeat neodpovídá nebo změnil formát.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * Výpadek, odmítnutý token hledání, nebo stránka či odpověď bez dat hry —
 * délka hraní se tentokrát nestáhne, dosavadní zůstane (R44).
 */
final class HltbUnavailable extends RuntimeException implements SourceUnavailable {}
