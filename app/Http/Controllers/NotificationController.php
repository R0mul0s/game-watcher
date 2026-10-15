<?php

/**
 * Centrum upozornění — seznam záznamů, otevření záznamu, označení přečtených.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Notifications\NotificationPresenter;
use App\Http\Requests\MarkNotificationsReadRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centrum upozornění (R53, /upozorneni) podle Agregátoru letáků: záznamy za
 * posledních games.notifications.retention_days dní po dnech, zvonek
 * v hlavičce s počtem nepřečtených. Samotný seznam nic neoznačí — záznam je
 * přečtený až po otevření (odkaz ze seznamu i ze zprávy na zařízení), všechny
 * najednou tlačítko „Označit vše jako přečtené“. Uživatel vidí jen svoje.
 */
class NotificationController extends Controller
{
    /**
     * Seznam záznamů od nejnovějšího.
     */
    public function index(Request $request, NotificationPresenter $presenter): Response
    {
        $notifications = $this->user($request)->notifications()
            ->limit((int) config('games.notifications.max_listed'))
            ->get()
            ->map(fn (DatabaseNotification $notification): ?array => $presenter->summary($notification))
            ->filter()
            ->values();

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'readUrl' => route('notifications.read', absolute: false),
            'dashboardUrl' => route('dashboard', absolute: false),
            'retentionDays' => (int) config('games.notifications.retention_days'),
        ]);
    }

    /**
     * Otevře záznam: označí ho jako přečtený a přejde na to, čeho se týká
     * (detail hry, přehled, stav zdrojů). Jen přes GET, protože tam vede
     * i klepnutí na zprávu na zařízení; označení je idempotentní a service
     * worker stránky s daty neukládá (R37).
     */
    public function open(Request $request, string $notification, NotificationPresenter $presenter): RedirectResponse
    {
        /** @var DatabaseNotification $record */
        $record = $this->user($request)->notifications()->findOrFail($notification);
        $record->markAsRead();

        return redirect($presenter->target($record));
    }

    /**
     * Označí záznamy jako přečtené (zvonek i číslo na ikoně aplikace se sníží).
     */
    public function markRead(MarkNotificationsReadRequest $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()
            ->whereIn('id', $request->ids())
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * Přihlášený uživatel — routy jsou za middlewarem `auth`.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
