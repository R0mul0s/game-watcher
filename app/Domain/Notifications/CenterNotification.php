<?php

/**
 * Záznam centra upozornění jako databázová notifikace Laravelu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\Notification;

/**
 * Jediný kanál je databáze (R53) — e-mail a zprávy na zařízení se skládají
 * ze záznamů zvlášť (SendAlertEmails, SendPriceAlerts). Odesílá se synchronně,
 * nesmí implementovat ShouldQueue (hosting nemá frontu, R2). Do sloupce type
 * jde krátký kód druhu místo názvu třídy.
 */
final class CenterNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly NotificationKind $kind,
        public readonly array $data,
    ) {}

    /**
     * Kanály — jen databáze.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Hodnota sloupce notifications.type.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->kind->value;
    }

    /**
     * Obsah záznamu (notifications.data).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }
}
