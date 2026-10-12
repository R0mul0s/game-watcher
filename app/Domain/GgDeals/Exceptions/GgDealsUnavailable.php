<?php

/**
 * gg.deals API neodpovídá nebo vrátilo nečekanou odpověď.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Domain\GgDeals\Exceptions;

use RuntimeException;

/**
 * Zpráva nikdy neobsahuje API klíč — gg.deals ho bere jen v URL, a ta se
 * v chybách HTTP klienta objevuje; proto výjimka ani nenese původní výjimku.
 */
final class GgDealsUnavailable extends RuntimeException {}
