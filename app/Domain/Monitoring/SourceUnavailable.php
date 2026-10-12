<?php

/**
 * Výpadek externího zdroje, jehož zprávu smí ukázat stav zdrojů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Monitoring;

use Throwable;

/**
 * Výjimky klientů zdrojů (ITAD, gg.deals, ČNB, PlayStation Store, Steam) —
 * zpráva nikdy nenese API klíč (ITAD ho posílá v hlavičce, gg.deals ho ze zprávy
 * maže), takže ji stav zdrojů může uložit a ukázat (R29). Zpráva jiné výjimky
 * se neukládá — mohla by obsahovat cokoli.
 */
interface SourceUnavailable extends Throwable {}
