<?php

/**
 * IGDB nebo přihlášení přes Twitch neodpovídá nebo změnilo formát.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Igdb\Exceptions;

use App\Domain\Monitoring\SourceUnavailable;
use RuntimeException;

/**
 * Výpadek, odmítnutý klíč aplikace nebo odpověď bez očekávaných dat —
 * seznam nadcházejících her se tentokrát neobnoví, dosavadní zůstane (R46).
 * Zpráva nesmí obsahovat klíč aplikace (ukazuje ji stav zdrojů, R29).
 */
final class IgdbUnavailable extends RuntimeException implements SourceUnavailable {}
