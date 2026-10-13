<?php

/**
 * Týdenní souhrn slev e-mailem (R36).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendWeeklyDigests;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Mail\WeeklyDigestMail;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Pondělí — den souhrnu (games.alerts.weekly_digest_day). */
const DIGEST_MONDAY = '2026-10-19 08:00';

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    // BIOMUTANT teď: nejlevněji klíče 5,41 € (132 Kč)
    $this->user = watcherOf([BIOMUTANT_ID]);
    $this->game = Game::query()->sole();
    Mail::fake();
});

/**
 * Nejnižší cena hry v historii před týdnem (den v místní zóně).
 */
function priceWeekAgo(Game $game, string $amount, string $currency = 'EUR'): void
{
    DB::table('price_history')->insert([
        'game_id' => $game->id,
        'shop_key' => 'keyshops',
        'day' => CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague')->subDays((int) config('games.alerts.weekly_digest_days'))->toDateString(),
        'amount' => $amount,
        'currency' => $currency,
    ]);
}

it('v pondělí pošle souhrn her, které za týden zlevnily, jednou za den', function (): void {
    priceWeekAgo($this->game, '10.00');
    $this->travelTo(CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague'));

    expect(app(SendWeeklyDigests::class)->handle())->toBe([1, 0]);
    Mail::assertSent(WeeklyDigestMail::class, fn (WeeklyDigestMail $mail): bool => $mail->hasTo($this->user->email)
        && $mail->lines[0]['title'] === 'BIOMUTANT'
        && $mail->lines[0]['drop'] === 46
        && str_replace(["\u{a0}", "\u{202f}"], ' ', $mail->lines[0]['previous']) === '244 Kč');

    // Další běh téhož dne už nic
    $this->travel(4)->hours();
    expect(app(SendWeeklyDigests::class)->handle())->toBe([0, 0]);
    Mail::assertSentCount(1);
});

it('mimo den souhrnu nic nepošle', function (): void {
    priceWeekAgo($this->game, '10.00');
    $this->travelTo(CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague')->addDay());

    expect(app(SendWeeklyDigests::class)->handle())->toBe([0, 0]);
    Mail::assertNothingSent();
});

it('bez slev nic nepošle, den ale označí', function (): void {
    $this->travelTo(CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague'));

    expect(app(SendWeeklyDigests::class)->handle())->toBe([0, 0]);
    Mail::assertNothingSent();
    expect($this->user->fresh()?->weekly_digest_at)->not->toBeNull();
});

it('účtu s vypnutým souhrnem nebo e-mailem nic nepošle', function (array $settings): void {
    priceWeekAgo($this->game, '10.00');
    $this->user->update($settings);
    $this->travelTo(CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague'));

    app(SendWeeklyDigests::class)->handle();

    Mail::assertNothingSent();
})->with([
    'souhrn vypnutý' => [['notify_weekly' => false]],
    'e-mail vypnutý' => [['notify_email' => false]],
]);

it('cron vypíše řádek souhrnu', function (): void {
    priceWeekAgo($this->game, '10.00');
    $this->travelTo(CarbonImmutable::parse(DIGEST_MONDAY, 'Europe/Prague'));

    $this->get('/cron/ceny?token=test-cron-token')->assertOk()->assertSeeText('týdenní souhrn — e-mailů: 1');
});
