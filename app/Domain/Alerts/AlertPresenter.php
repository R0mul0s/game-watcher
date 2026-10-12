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
            'price' => $this->money->format($alert->price),
            'shop' => $alert->keyshops ? __('app.alerts.in_keyshops') : __('app.alerts.in_shop', ['shop' => $alert->shop]),
            'reason' => $this->reason($alert),
            'detailUrl' => route('games.show', $alert->game),
            'shopUrl' => $alert->url,
        ];
    }

    /**
     * Předmět e-mailu — u jedné hry hra a cena, jinak počet her.
     *
     * @param  list<PriceAlert>  $alerts
     */
    public function mailSubject(array $alerts): string
    {
        return count($alerts) === 1
            ? __('app.alerts.subject_one', ['title' => $alerts[0]->game->title, 'price' => $this->money->format($alerts[0]->price)])
            : trans_choice('app.alerts.subject_many', count($alerts), ['count' => count($alerts)]);
    }

    /**
     * Zpráva na telefon pro service worker: { title, body, url, tag }.
     *
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function push(PriceAlert $alert): array
    {
        return [
            'title' => __('app.alerts.push_title', ['title' => $alert->game->title, 'price' => $this->money->format($alert->price)]),
            'body' => __('app.alerts.push_body', ['reason' => $this->reason($alert), 'shop' => $alert->keyshops ? __('app.alerts.keyshops') : $alert->shop]),
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
     * Proč upozornění přišlo — „pod cílovou cenou 500 Kč“, „historické minimum“.
     */
    private function reason(PriceAlert $alert): string
    {
        return $alert->reason === AlertReason::Target && $alert->target !== null
            ? __('app.alerts.reason_target', ['target' => $this->money->format($alert->target)])
            : __('app.alerts.reason_low');
    }
}
