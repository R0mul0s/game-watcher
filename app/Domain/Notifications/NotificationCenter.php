<?php

/**
 * Zápis záznamů do centra upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Jediné místo, kudy záznam do centra vzniká (R53). Záznam dostane účet vždy,
 * bez ohledu na e-mail a zařízení — centrum je kanál, který doručí vždycky.
 */
final class NotificationCenter
{
    /**
     * Zapíše záznam účtu; vrátí jeho ID (cíl odkazu ve zprávě na zařízení).
     *
     * @param  array<string, mixed>  $data
     */
    public function record(User $user, NotificationKind $kind, array $data): string
    {
        $id = (string) Str::uuid();
        $notification = new CenterNotification($kind, $data);
        // ID předem — NotificationSender ho jinak vymyslí a nevrátí
        $notification->id = $id;
        $user->notify($notification);

        return $id;
    }

    /**
     * Počet nepřečtených záznamů — zvonek a číslo na ikoně aplikace (R37).
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
