<?php

/**
 * Odeslání zprávy Web Push na zařízení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Převzato z Počasí (R13) — knihovna minishlink/web-push s klientem Laravelu
 * (testy ho podvrhnou přes Http::fake). Klíče VAPID jsou v .env.
 */
final class PushSender
{
    private ?WebPush $webPush = null;

    /**
     * Jestli jsou upozornění na telefon zapnutá (vyplněné klíče VAPID).
     */
    public static function isEnabled(): bool
    {
        return config('games.push.public_key') !== '' && config('games.push.private_key') !== '';
    }

    /**
     * Pošle zprávu na zařízení. Odběr, který push služba označí za neplatný
     * (404/410 — aplikace odinstalovaná, upozornění zakázaná), smaže; jinou
     * chybu zapíše do logu — výpadek push služby nesmí shodit cron.
     *
     * @param  array<string, mixed>  $payload  JSON pro service worker
     * @param  string  $topic  Nedoručená starší zpráva se stejným tématem se nahradí
     */
    public function deliver(PushSubscription $subscription, array $payload, string $topic): DeliveryResult
    {
        $report = $this->send($subscription, $payload, $topic);

        if ($report->isSuccess()) {
            return DeliveryResult::Sent;
        }

        if ($report->isSubscriptionExpired()) {
            $subscription->delete();

            return DeliveryResult::Expired;
        }

        Log::warning('Upozornění na telefon se nepodařilo odeslat.', [
            'host' => parse_url($subscription->endpoint, PHP_URL_HOST),
            'reason' => $report->getReason(),
        ]);

        return DeliveryResult::Failed;
    }

    /**
     * Zašifruje a odešle jednu zprávu.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(PushSubscription $subscription, array $payload, string $topic): MessageSentReport
    {
        return $this->webPush()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            [
                'TTL' => (int) config('games.push.ttl_seconds'),
                'urgency' => 'normal',
                'topic' => $topic,
            ],
        );
    }

    /**
     * Klient Web Push s klíči VAPID (vytvoří se jednou na běh).
     */
    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(
            ['VAPID' => [
                'subject' => (string) config('games.push.subject'),
                'publicKey' => (string) config('games.push.public_key'),
                'privateKey' => (string) config('games.push.private_key'),
            ]],
            client: Http::timeout((int) config('games.push.timeout_seconds'))->buildClient(),
        );
    }
}
