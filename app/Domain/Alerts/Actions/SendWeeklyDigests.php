<?php

/**
 * Týdenní souhrn slev e-mailem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Alerts\Actions;

use App\Domain\Pricing\MoneyFormatter;
use App\Domain\Watchlist\DailyDeals;
use App\Domain\Watchlist\PriceDrop;
use App\Enums\Platform;
use App\Mail\WeeklyDigestMail;
use App\Models\User;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Krok cron URL (R36): v den games.alerts.weekly_digest_day pošle každému účtu
 * se zapnutým e-mailem a souhrnem jeden e-mail s hrami, které za posledních
 * weekly_digest_days dní zlevnily (stejný výpočet jako slevy dne — DailyDeals).
 * Odejde jednou za den souhrnu (první běh cronu, `weekly_digest_at`); bez
 * slev se nic neposílá, jen se den označí jako vyřízený. Chyba SMTP souhrn
 * neoznačí — zkusí ho další běh téhož dne. Synchronně, bez fronty (R2).
 */
final class SendWeeklyDigests
{
    public function __construct(
        private readonly DailyDeals $dailyDeals,
        private readonly MoneyFormatter $money,
    ) {}

    /**
     * Rozešle souhrny; vrátí počet odeslaných a nedoručených e-mailů. Mimo den
     * souhrnu nedělá nic.
     *
     * @return array{0: int, 1: int}
     */
    public function handle(): array
    {
        $today = CarbonImmutable::now((string) config('games.display_timezone'))->startOfDay();
        if ($today->dayOfWeekIso !== (int) config('games.alerts.weekly_digest_day')) {
            return [0, 0];
        }

        $users = User::query()
            ->where('notify_email', true)
            ->where('notify_weekly', true)
            ->where(fn (Builder $query) => $query->whereNull('weekly_digest_at')->orWhere('weekly_digest_at', '<', $today->utc()))
            ->orderBy('id')
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            $drops = $this->drops($user);
            if ($drops !== [] && ! $this->send($user, $drops)) {
                $failed++;

                continue;
            }

            $sent += $drops === [] ? 0 : 1;
            $user->update(['weekly_digest_at' => CarbonImmutable::now()]);
        }

        return [$sent, $failed];
    }

    /**
     * Hry účtu, které za týden zlevnily — nejvýš weekly_digest_limit.
     *
     * @return list<PriceDrop>
     */
    private function drops(User $user): array
    {
        $games = $user->watchedGames()->with(['currentPrices', 'priceLows'])->get();
        $platforms = $user->watchlistItemsByGame()->map(fn (WatchlistItem $item): ?Platform => $item->platform);

        return array_slice(
            $this->dailyDeals->drops($games, $platforms, $user->displayCurrency(), (int) config('games.alerts.weekly_digest_days')),
            0,
            (int) config('games.alerts.weekly_digest_limit'),
        );
    }

    /**
     * E-mail se souhrnem; chybu SMTP zapíše do logu.
     *
     * @param  list<PriceDrop>  $drops
     */
    private function send(User $user, array $drops): bool
    {
        try {
            Mail::to($user)->send(new WeeklyDigestMail(
                (string) trans_choice('app.alerts.weekly_subject', count($drops), ['count' => count($drops)]),
                array_map(fn (PriceDrop $drop): array => [
                    'title' => $drop->game->title,
                    'price' => $this->money->format($drop->price),
                    'previous' => $this->money->format($drop->previous),
                    'drop' => $drop->drop,
                    'detailUrl' => route('games.show', $drop->game),
                ], $drops),
            ));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}
