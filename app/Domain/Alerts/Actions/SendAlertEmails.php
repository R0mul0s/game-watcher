<?php

/**
 * E-mail s upozorněními ze záznamů centra — souhrn ráno, nebo hned.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\AlertPresenter;
use App\Domain\Notifications\GameAlertRecord;
use App\Enums\EmailFrequency;
use App\Enums\NotificationKind;
use App\Mail\PriceAlertMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Krok cron URL po upozorněních (R53) — synchronně, bez fronty (R2). Účtu
 * pošle jeden e-mail se záznamy „upozornění na hru“, které od minulého
 * e-mailu přibyly v centru (users.alert_email_sent_at). Podle volby účtu:
 * souhrn denně — první běh cronu od games.alerts.digest_hour místního času —
 * nebo po každém běhu, který něco zapsal. Hra, na kterou za den přišlo víc
 * upozornění (cena klesla dvakrát), je v e-mailu jednou s posledním. Chyba
 * SMTP čas neposune — e-mail zkusí další běh.
 */
final class SendAlertEmails
{
    public function __construct(private readonly AlertPresenter $presenter) {}

    /**
     * Pošle e-maily účtům, kterým je čas; vrátí počet odeslaných a nedoručených.
     *
     * @return array{0: int, 1: int}
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $users = User::query()->where('email_frequency', '!=', EmailFrequency::Off->value)->orderBy('id')->get();

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            if (! $this->isDue($user, $now)) {
                continue;
            }

            $records = $this->pending($user, $now);
            if ($records !== []) {
                if (! $this->send($user, $records)) {
                    $failed++;

                    continue;
                }
                $sent++;
            }

            // I bez záznamů — denní souhrn je pro dnešek vyřízený
            $user->update(['alert_email_sent_at' => $now]);
        }

        return [$sent, $failed];
    }

    /**
     * Je čas na e-mail? Hned vždy; denně jednou, od hodiny souhrnu.
     */
    private function isDue(User $user, CarbonImmutable $now): bool
    {
        if ($user->email_frequency === EmailFrequency::Instant) {
            return true;
        }

        $digestAt = $now->setTimezone((string) config('games.display_timezone'))
            ->startOfDay()
            ->setHour((int) config('games.alerts.digest_hour'));

        return $now->greaterThanOrEqualTo($digestAt)
            && ($user->alert_email_sent_at === null || $user->alert_email_sent_at->lessThan($digestAt));
    }

    /**
     * Upozornění na hry od minulého e-mailu, od nejstaršího; z víc záznamů
     * téže hry jen poslední.
     *
     * @return list<GameAlertRecord>
     */
    private function pending(User $user, CarbonImmutable $now): array
    {
        $records = [];
        $user->notifications()
            ->reorder()
            ->where('type', NotificationKind::GameAlert->value)
            ->when($user->alert_email_sent_at, fn ($query, CarbonImmutable $since) => $query->where('created_at', '>', $since))
            ->where('created_at', '<=', $now)
            ->orderBy('created_at')
            ->get()
            ->each(function (DatabaseNotification $notification) use (&$records): void {
                $record = GameAlertRecord::fromArray($notification->data);
                if ($record !== null) {
                    // Novější záznam téže hry nahradí starší a zařadí se podle sebe
                    unset($records[$record->gameId]);
                    $records[$record->gameId] = $record;
                }
            });

        return array_values($records);
    }

    /**
     * Jeden e-mail se všemi hrami; chybu SMTP zapíše do logu.
     *
     * @param  non-empty-list<GameAlertRecord>  $records
     */
    private function send(User $user, array $records): bool
    {
        try {
            Mail::to($user)->send(new PriceAlertMail(
                $this->presenter->mailSubject($records),
                array_map($this->presenter->mailLine(...), $records),
            ));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}
