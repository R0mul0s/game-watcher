<?php

/**
 * Výsledek odeslání zprávy Web Push.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

/**
 * Převzato z Počasí (R13).
 */
enum DeliveryResult: string
{
    /** Push služba zprávu přijala. */
    case Sent = 'sent';

    /** Push služba odběr zrušila (404/410) — odběr je smazaný. */
    case Expired = 'expired';

    /** Jiná chyba (výpadek služby, síť) — zapsaná do logu, odběr zůstává. */
    case Failed = 'failed';
}
