<?php

/**
 * Odkaz nevede na produkt PlayStation Store, který jde koupit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Exceptions;

use RuntimeException;

/**
 * Jiná adresa než PS Store, neexistující produkt nebo koncept bez výchozího produktu.
 */
final class PlayStationProductNotFound extends RuntimeException {}
