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
use App\Domain\Alerts\OfferWatch;
use App\Domain\Alerts\PriceAlert;
use App\Domain\Alerts\PriceWatch;
use App\Domain\Alerts\PushSender;
use App\Domain\Alerts\ReleaseWatch;
use App\Domain\Notifications\GameAlertRecord;
use App\Domain\Notifications\NotificationCenter;
use App\Enums\NotificationKind;
use App\Models\Game;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Krok cron URL po stažení cen (R26) — synchronně, bez fronty (R2). Každé
 * upozornění se zapíše do centra upozornění (R53) a hned se označí jako
 * odeslané — centrum doručí vždy, i účtu bez e-mailu a zařízení. Na zařízení
 * jde zpráva hned; e-mail skládá ze záznamů centra až SendAlertEmails (ráno,
 * nebo hned podle účtu). Hra bez upozornění na cenu může dostat upozornění na
 * vydání nebo první slevu (ReleaseWatch, R41), a když ani to, na Game Pass
 * nebo bundle (OfferWatch, R50, R51) — za běh nejvýš jedno na hru.
 */
final class SendPriceAlerts
{
    public function __construct(
        private readonly PriceWatch $priceWatch,
        private readonly AlertPresenter $presenter,
        private readonly PushSender $pushSender,
        private readonly ReleaseWatch $releaseWatch,
        private readonly OfferWatch $offerWatch,
        private readonly NotificationCenter $center,
    ) {}

    /**
     * Vyhodnotí upozornění všech účtů, zapíše je do centra a pošle na zařízení.
     */
    public function handle(): AlertRunResult
    {
        $result = new AlertRunResult;
        $users = User::query()->with('pushSubscriptions')->orderBy('id')->get();

        foreach ($users as $user) {
            $alerts = $this->evaluate($user);
            if ($alerts === []) {
                continue;
            }

            // Záznamy a stav „už upozorněno“ spolu — pád mezi nimi by upozornění zdvojil
            $records = DB::transaction(fn (): array => $this->record($user, $alerts));
            $result->alerts += count($alerts);

            if (PushSender::isEnabled() && $user->pushSubscriptions->isNotEmpty()) {
                $result->pushes += $this->push($user, $records);
            }
        }

        return $result;
    }

    /**
     * Zapíše upozornění do centra a označí je jako odeslaná.
     *
     * @param  list<PriceAlert>  $alerts
     * @return array<string, GameAlertRecord> záznamy podle ID v centru
     */
    private function record(User $user, array $alerts): array
    {
        $records = [];
        foreach ($alerts as $alert) {
            $record = $this->presenter->record($alert);
            $records[$this->center->record($user, NotificationKind::GameAlert, $record->toArray())] = $record;
        }

        $this->markNotified($alerts);

        return $records;
    }

    /**
     * Upozornění účtu; položkám, u kterých podmínka přestala platit, smaže
     * poslední upozorněnou cenu (příští pokles zase upozorní).
     *
     * @return list<PriceAlert>
     */
    private function evaluate(User $user): array
    {
        $items = $user->watchlistItems()->with(['game.currentPrices', 'game.priceLows', 'game.bundles'])->orderBy('id')->get();
        $alerts = [];

        foreach ($items as $item) {
            $game = $item->game;
            if (! $game instanceof Game) {
                continue;
            }

            $check = $this->priceWatch->check($user, $item, $game);

            if ($check->alert !== null) {
                $alerts[] = $check->alert;

                continue;
            }

            if (! $check->met && $item->notified_amount !== null) {
                $item->update(['notified_amount' => null, 'notified_currency' => null]);
            }

            $release = $this->releaseWatch->check($user, $item, $game);
            if ($release !== null) {
                $alerts[] = $release;

                continue;
            }

            // Game Pass a bundly (R50, R51) — až když hra nemá nic důležitějšího
            $offer = $this->offerWatch->check($user, $item, $game);
            if ($offer !== null) {
                $alerts[] = $offer;
            }
        }

        return $alerts;
    }

    /**
     * Zprávy na všechna zařízení účtu — po hrách, nebo jedna souhrnná, když
     * jich zlevnilo víc než alerts.max_push_messages. Klepnutí otevře záznam
     * v centru (a tím ho přečte). Vrátí počet doručených; nedoručená zpráva
     * se neopakuje, záznam v centru zůstává (R53).
     *
     * @param  array<string, GameAlertRecord>  $records  záznamy podle ID v centru
     */
    private function push(User $user, array $records): int
    {
        // Počet nepřečtených záznamů centra jako číslo na ikoně aplikace (R37, R53)
        $badge = ['badge' => $this->center->unreadCount($user)];
        $messages = count($records) > (int) config('games.alerts.max_push_messages')
            ? [[
                'payload' => [...$this->presenter->pushSummary(array_values($records), route('notifications.index', absolute: false)), ...$badge],
                'topic' => AlertPresenter::SUMMARY_TOPIC,
            ]]
            : array_map(fn (string $id, GameAlertRecord $record): array => [
                'payload' => [...$this->presenter->push($record, route('notifications.open', $id, absolute: false)), ...$badge],
                'topic' => $this->presenter->topic($record),
            ], array_keys($records), $records);

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
     * Zapamatuje upozorněnou cenu — stejná cena podruhé nepřijde — a vydání
     * či první slevu, které upozornění pokrylo (R41).
     *
     * @param  list<PriceAlert>  $alerts
     */
    private function markNotified(array $alerts): void
    {
        $now = CarbonImmutable::now();
        foreach ($alerts as $alert) {
            // Game Pass a bundle cenu v obchodě neříkají — jen vlastní stav (R50, R51)
            if ($alert->reason->isOffer()) {
                $this->offerWatch->markNotified($alert, $now);

                continue;
            }

            if ($alert->reason->isPriceCondition() && $alert->price !== null) {
                $alert->item->update([
                    'notified_amount' => $alert->price->toDecimal(),
                    'notified_currency' => $alert->price->currency,
                    'notified_at' => $now,
                ]);
            }

            $this->releaseWatch->markCovered($alert, $now);
        }
    }
}
