<?php

/**
 * Záznam pomalé akce s časy volání zdrojů do logu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Monitoring;

use Closure;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Změří akci (hledání hry, R56) a když trvá déle než
 * games.monitoring.slow_request_seconds, zapíše do logu každé volání zdroje
 * během ní — hostitel, stav, celkový čas a z čeho se skládá (DNS, spojení,
 * TLS) a jestli šlo přes IPv4, nebo IPv6. Na hostingu se tak pozná, co je
 * pomalé, bez SSH. Do logu jde jen hostitel a cesta, nikdy dotaz — gg.deals
 * a ITAD mají klíč v adrese (R18).
 */
final class SlowRequestLog
{
    /** Čas v sekundách v řádku logu. */
    private const SECONDS_FORMAT = '%.2f';

    /** @var list<string> volání zdrojů během měřené akce */
    private array $calls = [];

    private bool $measuring = false;

    private bool $listening = false;

    /**
     * Provede akci a vrátí její výsledek; pomalou zapíše do logu.
     *
     * @template T
     *
     * @param  string  $action  popis akce v logu („hledání „Horizon““)
     * @param  Closure(): T  $task
     * @return T
     */
    public function measure(string $action, Closure $task): mixed
    {
        $this->listen();
        $this->calls = [];
        $this->measuring = true;
        $started = microtime(true);

        try {
            return $task();
        } finally {
            $this->measuring = false;
            $seconds = microtime(true) - $started;
            if ($seconds >= (float) config('games.monitoring.slow_request_seconds')) {
                Log::warning(sprintf('Pomalé %s: '.self::SECONDS_FORMAT.' s, volání zdrojů: %d', $action, $seconds, count($this->calls)), ['calls' => $this->calls]);
            }
        }
    }

    /**
     * Zaregistruje posluchače HTTP klienta (jednou za instanci).
     */
    private function listen(): void
    {
        if ($this->listening) {
            return;
        }
        $this->listening = true;

        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            if ($this->measuring) {
                $this->calls[] = $this->describe($event->request->method(), $event->request->url(), (string) $event->response->status(), $event->response->handlerStats());
            }
        });
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            if ($this->measuring) {
                $this->calls[] = $this->describe($event->request->method(), $event->request->url(), 'bez odpovědi', []);
            }
        });
    }

    /**
     * Řádek jednoho volání: „GET api.isthereanydeal.com/games/search/v1 200 —
     * 3.10 s (DNS 2.50, spojení 2.60, TLS 2.80, IPv6)“ — dílčí časy cURL se
     * počítají od začátku požadavku (spojení hotové v 2,60 s).
     *
     * @param  array<string, mixed>  $stats  údaje cURL o přenosu
     */
    private function describe(string $method, string $url, string $status, array $stats): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $time = fn (string $key): string => is_numeric($stats[$key] ?? null) ? sprintf(self::SECONDS_FORMAT, (float) $stats[$key]) : '?';
        $ip = (string) ($stats['primary_ip'] ?? '');

        return sprintf(
            '%s %s%s %s — %s s (DNS %s, spojení %s, TLS %s, %s)',
            $method,
            $host,
            $path,
            $status,
            $time('total_time'),
            $time('namelookup_time'),
            $time('connect_time'),
            $time('appconnect_time'),
            $ip === '' ? 'IP ?' : (str_contains($ip, ':') ? 'IPv6' : 'IPv4'),
        );
    }
}
