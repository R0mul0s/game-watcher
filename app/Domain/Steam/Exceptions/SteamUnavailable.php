<?php

/**
 * Steam neodpovídá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Steam\Exceptions;

use RuntimeException;

/**
 * Výpadek, limit požadavků (HTTP 429) nebo změněný tvar dat — údaje se
 * zkusí stáhnout při dalším běhu cronu, podrobnosti v logu.
 */
final class SteamUnavailable extends RuntimeException {}
