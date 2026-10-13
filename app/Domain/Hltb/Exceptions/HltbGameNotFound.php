<?php

/**
 * Odkaz na HowLongToBeat nevede na hru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb\Exceptions;

use RuntimeException;

/**
 * Jiný hostitel, tvar adresy, nebo hra, kterou HowLongToBeat nezná (R44).
 */
final class HltbGameNotFound extends RuntimeException {}
