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

use App\Domain\Notifications\GameAlertRecord;
use App\Domain\Pricing\MoneyFormatter;
use App\Enums\AlertReason;

/**
 * Jedno místo, kde se z upozornění stává text (R26) — záznam v centru
 * upozornění (R53), e-mail i zpráva na telefon říkají totéž. Texty jsou
 * v lang/cs/app.php (skupina alerts).
 */
final class AlertPresenter
{
    /** Téma zprávy Web Push — nedoručená starší zpráva téže hry se nahradí (max. 32 znaků). */
    private const TOPIC_PREFIX = 'game-';

    /** Téma souhrnné zprávy za víc her. */
    public const SUMMARY_TOPIC = 'summary';

    /** Den konce nabídky v textu („27. 10.“). */
    private const DAY_FORMAT = 'j. n.';

    public function __construct(private readonly MoneyFormatter $money) {}

    /**
     * Záznam do centra upozornění (R53) — všechny texty najednou, z něj se
     * pak skládá zpráva na zařízení i řádek e-mailu.
     */
    public function record(PriceAlert $alert): GameAlertRecord
    {
        return new GameAlertRecord(
            gameId: $alert->game->id,
            game: $alert->game->title,
            headline: $this->headline($alert),
            body: $alert->reason === AlertReason::GamePass
                ? $this->reason($alert)
                : (string) __('app.alerts.push_body', ['reason' => $this->reason($alert), 'shop' => $alert->keyshops ? __('app.alerts.keyshops') : $alert->shop]),
            // Game Pass a bundle bez ceny cenu nemají (R50, R51)
            price: $alert->price === null ? '' : $this->money->format($alert->price),
            shop: $this->where($alert),
            reason: $this->reason($alert),
            news: $alert->reason->isNews(),
            url: route('games.show', $alert->game, absolute: false),
            shopUrl: $alert->url,
        );
    }

    /**
     * Řádek e-mailu.
     *
     * @return array{title: string, price: string, shop: string, reason: string, detailUrl: string, shopUrl: string|null}
     */
    public function mailLine(GameAlertRecord $record): array
    {
        return [
            'title' => $record->game,
            'price' => $record->price,
            'shop' => $record->shop,
            'reason' => $record->reason,
            'detailUrl' => url($record->url),
            'shopUrl' => $record->shopUrl,
        ];
    }

    /**
     * Předmět e-mailu — u jedné hry hra a cena, jinak počet her; s novinkou
     * (vydání R41, Game Pass, bundle) mezi nimi „novinky“ místo „zlevnilo“.
     *
     * @param  non-empty-list<GameAlertRecord>  $records
     */
    public function mailSubject(array $records): string
    {
        if (count($records) === 1) {
            return $records[0]->headline;
        }

        $news = array_filter($records, fn (GameAlertRecord $record): bool => $record->news) !== [];

        return trans_choice($news ? 'app.alerts.subject_news' : 'app.alerts.subject_many', count($records), ['count' => count($records)]);
    }

    /**
     * Zpráva na telefon pro service worker: { title, body, url, tag }.
     *
     * @param  string  $url  kam vede klepnutí — otevření záznamu v centru
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function push(GameAlertRecord $record, string $url): array
    {
        return [
            'title' => $record->headline,
            'body' => $record->body,
            'url' => $url,
            'tag' => $this->topic($record),
        ];
    }

    /**
     * Souhrnná zpráva na telefon, když zlevnilo víc her najednou.
     *
     * @param  list<GameAlertRecord>  $records
     * @param  string  $url  kam vede klepnutí — centrum upozornění
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function pushSummary(array $records, string $url): array
    {
        return [
            'title' => trans_choice('app.alerts.subject_many', count($records), ['count' => count($records)]),
            'body' => implode(', ', array_map(fn (GameAlertRecord $record): string => $record->game, $records)),
            'url' => $url,
            'tag' => self::SUMMARY_TOPIC,
        ];
    }

    /**
     * Téma zprávy Web Push pro hru.
     */
    public function topic(GameAlertRecord $record): string
    {
        return self::TOPIC_PREFIX.$record->gameId;
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
