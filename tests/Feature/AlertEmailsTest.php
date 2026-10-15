<?php

/**
 * E-mail s upozorněními ze záznamů centra — ranní souhrn, hned, vypnuto (R53).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendAlertEmails;
use App\Domain\Notifications\GameAlertRecord;
use App\Domain\Notifications\NotificationCenter;
use App\Enums\EmailFrequency;
use App\Enums\NotificationKind;
use App\Mail\PriceAlertMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/** Den testů — souhrn chodí od games.alerts.digest_hour (7:00) místního času. */
const EMAIL_DAY = '2026-10-15';

beforeEach(function (): void {
    Mail::fake();
});

/**
 * Upozornění na hru v centru účtu, jako by ho zapsal cron.
 */
function recordAlert(User $user, int $gameId, string $game, string $price = '299 Kč'): void
{
    app(NotificationCenter::class)->record($user, NotificationKind::GameAlert, (new GameAlertRecord(
        gameId: $gameId,
        game: $game,
        headline: $game.' za '.$price,
        body: 'historické minimum · Steam',
        price: $price,
        shop: 'v obchodě Steam',
        reason: 'historické minimum',
        news: false,
        url: '/hry/hra-'.$gameId,
        shopUrl: null,
    ))->toArray());
}

/**
 * Čas testu v místní zóně („07:30“ dne EMAIL_DAY, nebo celé datum).
 */
function emailTime(string $time): CarbonImmutable
{
    return CarbonImmutable::parse(str_contains($time, '-') ? $time : EMAIL_DAY.' '.$time, 'Europe/Prague');
}

it('ranní souhrn pošle první běh od 7:00 jednou za den', function (): void {
    $user = user();
    $this->travelTo(emailTime('03:10'));
    recordAlert($user, 1, 'Hades II');

    // Před hodinou souhrnu nic
    expect(app(SendAlertEmails::class)->handle())->toBe([0, 0]);
    Mail::assertNothingSent();

    $this->travelTo(emailTime('07:10'));
    expect(app(SendAlertEmails::class)->handle())->toBe([1, 0]);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->hasTo($user->email)
        && $mail->subjectLine === 'Hades II za 299 Kč'
        && $mail->lines[0]['detailUrl'] === url('/hry/hra-1'));

    // Upozornění během dne počká na zítřejší ráno
    $this->travelTo(emailTime('11:10'));
    recordAlert($user, 2, 'Hollow Knight: Silksong');
    expect(app(SendAlertEmails::class)->handle())->toBe([0, 0]);

    $this->travelTo(emailTime('2026-10-16 07:10'));
    expect(app(SendAlertEmails::class)->handle())->toBe([1, 0]);
    Mail::assertSentCount(2);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => count($mail->lines) === 1
        && $mail->lines[0]['title'] === 'Hollow Knight: Silksong');
});

it('ráno bez nových upozornění nic nepošle a den označí', function (): void {
    $user = user();
    $this->travelTo(emailTime('07:10'));

    expect(app(SendAlertEmails::class)->handle())->toBe([0, 0])
        ->and($user->fresh()?->alert_email_sent_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('volba „hned“ pošle po každém běhu jen nová upozornění', function (): void {
    $user = user(['email_frequency' => EmailFrequency::Instant]);
    $this->travelTo(emailTime('03:10'));
    recordAlert($user, 1, 'Hades II');

    expect(app(SendAlertEmails::class)->handle())->toBe([1, 0])
        ->and(app(SendAlertEmails::class)->handle())->toBe([0, 0]);

    $this->travelTo(emailTime('07:10'));
    recordAlert($user, 2, 'Hollow Knight: Silksong');
    expect(app(SendAlertEmails::class)->handle())->toBe([1, 0]);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->lines[0]['title'] === 'Hollow Knight: Silksong');
});

it('vypnutý e-mail nepošle nic', function (): void {
    $user = user(['email_frequency' => EmailFrequency::Off]);
    $this->travelTo(emailTime('07:10'));
    recordAlert($user, 1, 'Hades II');

    expect(app(SendAlertEmails::class)->handle())->toBe([0, 0]);
    Mail::assertNothingSent();
});

it('hru s víc upozorněními za den pošle jednou s posledním', function (): void {
    $user = user();
    $this->travelTo(emailTime('2026-10-14 11:10'));
    recordAlert($user, 1, 'Hades II', '399 Kč');
    $this->travelTo(emailTime('2026-10-14 15:10'));
    recordAlert($user, 2, 'Hollow Knight: Silksong');
    $this->travelTo(emailTime('2026-10-14 19:10'));
    recordAlert($user, 1, 'Hades II', '299 Kč');

    $this->travelTo(emailTime('07:10'));
    app(SendAlertEmails::class)->handle();

    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->subjectLine === 'Zlevnily 2 sledované hry'
        && array_column($mail->lines, 'title') === ['Hollow Knight: Silksong', 'Hades II']
        && $mail->lines[1]['price'] === '299 Kč');
});

it('chyba SMTP souhrn neoznačí — pošle ho další běh', function (): void {
    $user = user();
    $this->travelTo(emailTime('07:10'));
    recordAlert($user, 1, 'Hades II');
    Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('SMTP neodpovídá'));

    expect(app(SendAlertEmails::class)->handle())->toBe([0, 1])
        ->and($user->fresh()?->alert_email_sent_at)->toBeNull();

    // Další běh téhož dne to zkusí znovu
    $this->travelTo(emailTime('11:10'));
    expect(app(SendAlertEmails::class)->handle())->toBe([0, 1]);
});
