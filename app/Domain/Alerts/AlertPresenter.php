<?php

/**
 * Texty upozornění na cenu pro e-mail a telefon.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Pricing\MoneyFormatter;
use App\Enums\AlertReason;

/**
 * Jedno místo, kde se z upozornění stává text (R26) — e-mail i zpráva na
 * telefon říkají totéž. Texty jsou v lang/cs/app.php (skupina alerts).
 */
final class AlertPresenter
{
    /** Téma zprávy Web Push — nedoručená starší zpráva téže hry se nahradí (max. 32 znaků). */
    private const TOPIC_PREFIX = 'game-';

    /** Den konce nabídky v textu („27. 10.“). */
    private const DAY_FORMAT = 'j. n.';

    public function __construct(private readonly MoneyFormatter $money) {}

    /**
     * Řádek e-mailu.
     *
     * @return array{title: string, price: string, shop: string, reason: string, detailUrl: string, shopUrl: string|null}
     */
    public function mailLine(PriceAlert $alert): array
    {
        return [
            'title' => $alert->game->title,
            // Game Pass a bundle bez ceny cenu nemají (R50, R51)
            'price' => $alert->price === null ? '' : $this->money->format($alert->price),
            'shop' => $this->where($alert),
            'reason' => $this->reason($alert),
            'detailUrl' => route('games.show', $alert->game),
            'shopUrl' => $alert->url,
        ];
    }

    /**
     * Předmět e-mailu — u jedné hry hra a cena, jinak počet her; s vydanou
     * hrou (R41) mezi nimi „novinky“ místo „zlevnilo“.
     *
     * @param  list<PriceAlert>  $alerts
     */
    public function mailSubject(array $alerts): string
    {
        $news = array_filter($alerts, fn (PriceAlert $alert): bool => $alert->reason->isNews()) !== [];

        if (count($alerts) === 1) {
            return $this->headline($alerts[0]);
        }

        return trans_choice($news ? 'app.alerts.subject_news' : 'app.alerts.subject_many', count($alerts), ['count' => count($alerts)]);
    }

    /**
     * Zpráva na telefon pro service worker: { title, body, url, tag }.
     *
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function push(PriceAlert $alert): array
    {
        return [
            'title' => $this->headline($alert),
            'body' => $alert->reason === AlertReason::GamePass
                ? $this->reason($alert)
                : __('app.alerts.push_body', ['reason' => $this->reason($alert), 'shop' => $alert->keyshops ? __('app.alerts.keyshops') : $alert->shop]),
            'url' => route('games.show', $alert->game, absolute: false),
            'tag' => $this->topic($alert),
        ];
    }

    /**
     * Souhrnná zpráva na telefon, když zlevnilo víc her najednou.
     *
     * @param  list<PriceAlert>  $alerts
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function pushSummary(array $alerts): array
    {
        return [
            'title' => trans_choice('app.alerts.subject_many', count($alerts), ['count' => count($alerts)]),
            'body' => implode(', ', array_map(fn (PriceAlert $alert): string => $alert->game->title, $alerts)),
            'url' => route('dashboard', absolute: false),
            'tag' => 'summary',
        ];
    }

    /**
     * Téma zprávy Web Push pro hru.
     */
    public function topic(PriceAlert $alert): string
    {
        return self::TOPIC_PREFIX.$alert->game->id;
    }

    /**
     * Nadpis jednoho upozornění (předmět e-mailu, titulek zprávy) — hra a cena,
     * u vydání „Právě vyšlo“, u Game Passu a bundlu bez ceny hra a důvod.
     */
    private function headline(PriceAlert $alert): string
    {
        $title = $alert->game->title;

        return match (true) {
            $alert->price === null => (string) __('app.alerts.subject_offer', ['title' => $title, 'reason' => $this->reason($alert)]),
            $alert->reason === AlertReason::Released => (string) __('app.alerts.subject_released', ['title' => $title, 'price' => $this->money->format($alert->price)]),
            $alert->reason === AlertReason::Bundle => (string) __('app.alerts.subject_bundle', ['title' => $title, 'price' => $this->money->format($alert->price)]),
            default => (string) __('app.alerts.subject_one', ['title' => $title, 'price' => $this->money->format($alert->price)]),
        };
    }

    /**
     * Kde nabídka je — obchod, obchody s klíči, bundle na webu obchodu; Game Pass nic.
     */
    private function where(PriceAlert $alert): string
    {
        return (string) match (true) {
            $alert->reason === AlertReason::GamePass => '',
            $alert->reason === AlertReason::Bundle => __('app.alerts.in_bundle_shop', ['shop' => $alert->shop]),
            $alert->keyshops => __('app.alerts.in_keyshops'),
            default => __('app.alerts.in_shop', ['shop' => $alert->shop]),
        };
    }

    /**
     * Do kdy nabídka platí — „ do 27. 10.“ v místním čase; prázdné, když konec neznáme.
     */
    private function until(PriceAlert $alert): string
    {
        return $alert->until === null
            ? ''
            : (string) __('app.alerts.until', ['date' => $alert->until->setTimezone((string) config('games.display_timezone'))->format(self::DAY_FORMAT)]);
    }

    /**
     * Proč upozornění přišlo — „pod cílovou cenou 500 Kč“, „historické minimum“,
     * „právě vyšlo“, „v Game Passu“, „v bundlu …“.
     */
    private function reason(PriceAlert $alert): string
    {
        return match (true) {
            $alert->reason === AlertReason::GamePass => (string) __('app.alerts.reason_game_pass').$this->until($alert),
            $alert->reason === AlertReason::Bundle => (string) __('app.alerts.reason_bundle', ['bundle' => $alert->bundle->title ?? '']).$this->until($alert),
            $alert->reason === AlertReason::PlusMonthly => (string) __('app.alerts.reason_plus_monthly'),
            $alert->reason === AlertReason::Released => (string) __('app.alerts.reason_released'),
            $alert->reason === AlertReason::FirstSale => (string) __('app.alerts.reason_first_sale'),
            $alert->reason === AlertReason::Target && $alert->target !== null => (string) __('app.alerts.reason_target', ['target' => $this->money->format($alert->target)]),
            default => (string) __('app.alerts.reason_low'),
        };
    }
}
