<?php

/**
 * Záznamy centra upozornění pro stránku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Z uloženého záznamu (R53) dělá řádek centra — nadpis, text, čas — a říká,
 * kam vede jeho otevření. Upozornění na hru nese hotové texty z doby
 * upozornění (GameAlertRecord), souhrn a výpadky se skládají z lang/cs/app.php.
 */
final class NotificationPresenter
{
    /** Oddělovač názvů her a zdrojů v textu záznamu. */
    private const LIST_SEPARATOR = ', ';

    /**
     * Řádek centra; null u neznámého druhu nebo poškozených dat (záznam se vynechá).
     *
     * @return array{id: string, kind: string, title: string, text: string, createdAt: string|null, unread: bool, url: string}|null
     */
    public function summary(DatabaseNotification $notification): ?array
    {
        $kind = NotificationKind::tryFrom((string) $notification->type);
        $texts = match ($kind) {
            NotificationKind::GameAlert => $this->gameAlert($notification),
            NotificationKind::WeeklyDigest => $this->weeklyDigest($notification),
            NotificationKind::Sources => $this->sources($notification),
            null => null,
        };

        if ($kind === null || $texts === null) {
            return null;
        }

        return [
            'id' => (string) $notification->id,
            'kind' => $kind->value,
            'title' => $texts[0],
            'text' => $texts[1],
            'createdAt' => $notification->created_at?->toIso8601String(),
            'unread' => $notification->read_at === null,
            'url' => route('notifications.open', $notification->id, absolute: false),
        ];
    }

    /**
     * Kam vede otevření záznamu — detail hry, přehled (souhrn), stav zdrojů.
     */
    public function target(DatabaseNotification $notification): string
    {
        return match (NotificationKind::tryFrom((string) $notification->type)) {
            NotificationKind::GameAlert => GameAlertRecord::fromArray($this->data($notification))->url ?? route('notifications.index', absolute: false),
            NotificationKind::WeeklyDigest => route('dashboard', absolute: false),
            NotificationKind::Sources => route('sources.show', absolute: false),
            null => route('notifications.index', absolute: false),
        };
    }

    /**
     * Upozornění na hru — nadpis a text jako zpráva na zařízení.
     *
     * @return array{0: string, 1: string}|null
     */
    private function gameAlert(DatabaseNotification $notification): ?array
    {
        $record = GameAlertRecord::fromArray($this->data($notification));

        return $record === null ? null : [$record->headline, $record->body];
    }

    /**
     * Týdenní souhrn — počet her a jejich názvy.
     *
     * @return array{0: string, 1: string}|null
     */
    private function weeklyDigest(DatabaseNotification $notification): ?array
    {
        $games = $this->strings($this->data($notification)['games'] ?? null);
        if ($games === []) {
            return null;
        }

        return [
            (string) trans_choice('app.alerts.weekly_subject', count($games), ['count' => count($games)]),
            implode(self::LIST_SEPARATOR, $games),
        ];
    }

    /**
     * Výpadek zdrojů se jmény zdrojů, nebo jejich obnova (prázdný seznam).
     *
     * @return array{0: string, 1: string}
     */
    private function sources(DatabaseNotification $notification): array
    {
        $down = $this->strings($this->data($notification)['down'] ?? null);
        if ($down === []) {
            return [(string) __('app.notifications.sources_up_title'), (string) __('app.notifications.sources_up_text')];
        }

        $names = array_map(fn (string $source): string => (string) __('app.ui.sources.names.'.$source), $down);

        return [
            (string) __('app.notifications.sources_down_title'),
            (string) __('app.notifications.sources_down_text', ['sources' => implode(self::LIST_SEPARATOR, $names)]),
        ];
    }

    /**
     * Uložená data záznamu (cast array — poškozený JSON dá prázdné pole).
     *
     * @return array<mixed>
     */
    private function data(DatabaseNotification $notification): array
    {
        return $notification->data ?? [];
    }

    /**
     * Seznam textů z uložených dat — ostatní hodnoty vynechá.
     *
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
