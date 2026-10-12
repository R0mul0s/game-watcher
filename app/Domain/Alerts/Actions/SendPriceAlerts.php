<?php

/**
 * Rozeslání upozornění na ceny sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\AlertPresenter;
use App\Domain\Alerts\AlertRunResult;
use App\Domain\Alerts\DeliveryResult;
use App\Domain\Alerts\PriceAlert;
use App\Domain\Alerts\PriceWatch;
use App\Domain\Alerts\PushSender;
use App\Mail\PriceAlertMail;
use App\Models\Game;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Krok cron URL po stažení cen (R26) — synchronně, bez fronty (R2). Účet
 * bez kanálu (e-mail vypnutý, žádné zařízení) se přeskočí a jeho stav se
 * nemění — po zapnutí kanálu dostane upozornění na platné ceny. Upozornění
 * se označí jako odeslané, jen když ho doručil aspoň jeden kanál; jinak ho
 * zkusí další běh.
 */
final class SendPriceAlerts
{
    public function __construct(
        private readonly PriceWatch $priceWatch,
        private readonly AlertPresenter $presenter,
        private readonly PushSender $pushSender,
    ) {}

    /**
     * Vyhodnotí a rozešle upozornění všech účtů.
     */
    public function handle(): AlertRunResult
    {
        $result = new AlertRunResult;
        $users = User::query()->with('pushSubscriptions')->orderBy('id')->get();

        foreach ($users as $user) {
            $push = PushSender::isEnabled() && $user->pushSubscriptions->isNotEmpty();
            if (! $user->notify_email && ! $push) {
                continue;
            }

            $alerts = $this->evaluate($user);
            if ($alerts === []) {
                continue;
            }

            $emailed = $user->notify_email && $this->email($user, $alerts);
            $pushed = $push ? $this->push($user, $alerts) : 0;

            $result->alerts += count($alerts);
            $result->emails += $emailed ? 1 : 0;
            $result->pushes += $pushed;

            if ($emailed || $pushed > 0) {
                $this->markNotified($alerts);
            } else {
                $result->undelivered += count($alerts);
            }
        }

        return $result;
    }

    /**
     * Upozornění účtu; položkám, u kterých podmínka přestala platit, smaže
     * poslední upozorněnou cenu (příští pokles zase upozorní).
     *
     * @return list<PriceAlert>
     */
    private function evaluate(User $user): array
    {
        $items = $user->watchlistItems()->with(['game.currentPrices', 'game.priceLows'])->orderBy('id')->get();
        $alerts = [];

        foreach ($items as $item) {
            $game = $item->game;
            if (! $game instanceof Game) {
                continue;
            }

            $check = $this->priceWatch->check($user, $item, $game);

            if ($check->alert !== null) {
                $alerts[] = $check->alert;
            } elseif (! $check->met && $item->notified_amount !== null) {
                $item->update(['notified_amount' => null, 'notified_currency' => null]);
            }
        }

        return $alerts;
    }

    /**
     * Jeden e-mail se všemi hrami; chybu SMTP zapíše do logu.
     *
     * @param  list<PriceAlert>  $alerts
     */
    private function email(User $user, array $alerts): bool
    {
        try {
            Mail::to($user)->send(new PriceAlertMail(
                $this->presenter->mailSubject($alerts),
                array_map($this->presenter->mailLine(...), $alerts),
            ));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Zprávy na všechna zařízení účtu — po hrách, nebo jedna souhrnná, když
     * jich zlevnilo víc než alerts.max_push_messages. Vrátí počet doručených.
     *
     * @param  list<PriceAlert>  $alerts
     */
    private function push(User $user, array $alerts): int
    {
        $messages = count($alerts) > (int) config('games.alerts.max_push_messages')
            ? [['payload' => $this->presenter->pushSummary($alerts), 'topic' => 'summary']]
            : array_map(fn (PriceAlert $alert): array => ['payload' => $this->presenter->push($alert), 'topic' => $this->presenter->topic($alert)], $alerts);

        $sent = 0;
        foreach ($user->pushSubscriptions as $subscription) {
            foreach ($messages as $message) {
                $delivery = $this->pushSender->deliver($subscription, $message['payload'], $message['topic']);
                if ($delivery === DeliveryResult::Expired) {
                    break;
                }

                $sent += $delivery === DeliveryResult::Sent ? 1 : 0;
            }
        }

        return $sent;
    }

    /**
     * Zapamatuje upozorněnou cenu — stejná cena podruhé nepřijde.
     *
     * @param  list<PriceAlert>  $alerts
     */
    private function markNotified(array $alerts): void
    {
        $now = CarbonImmutable::now();
        foreach ($alerts as $alert) {
            $alert->item->update([
                'notified_amount' => $alert->price->toDecimal(),
                'notified_currency' => $alert->price->currency,
                'notified_at' => $now,
            ]);
        }
    }
}
