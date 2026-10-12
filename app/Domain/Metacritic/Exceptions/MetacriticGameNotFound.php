<?php

/**
 * Odkaz na Metacritic nevede na hru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Metacritic\Exceptions;

use RuntimeException;

/**
 * Jiný hostitel, tvar adresy, nebo stránka, kterou Metacritic nezná (R33).
 */
final class MetacriticGameNotFound extends RuntimeException {}
