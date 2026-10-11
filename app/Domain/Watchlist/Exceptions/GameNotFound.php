<?php

/**
 * Hra s daným ITAD ID neexistuje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\Watchlist\Exceptions;

use RuntimeException;

/**
 * ITAD hru nezná — podvržené nebo mezitím zrušené ID z výsledků hledání.
 */
final class GameNotFound extends RuntimeException {}
