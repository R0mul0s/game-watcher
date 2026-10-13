<?php

/**
 * Upozornění na ceny — cílová cena, historické minimum, e-mail a telefon (etapa 6, R26).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendPriceAlerts;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Currency\ExchangeRates;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Domain\Pricing\Money;
use App\Enums\PriceScope;
use App\Mail\PriceAlertMail;
use App\Models\CurrentPrice;
use App\Models\PriceLow;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    // BIOMUTANT: nejlevněji klíče 5,41 € = 131,81 Kč (minimum klíčů 5,24 €),
    // oficiálně 24,96 € −30 % (minimum 6,72 €) — bez cíle žádné upozornění
    $this->user = watcherOf([BIOMUTANT_ID]);
    $this->item = WatchlistItem::query()->sole();
    Mail::fake();
});

/**
 * Nastaví cílovou cenu sledované hry.
 */
function setTarget(WatchlistItem $item, string $amount, string $currency = 'CZK'): void
{
    $item->update(['target_amount' => $amount, 'target_currency' => $currency]);
}

it('pošle e-mail, když nejlevnější nabídka (i klíče) klesne pod cílovou cenu', function (): void {
    setTarget($this->item, '200.00');

    $result = app(SendPriceAlerts::class)->handle();

    expect($result->alerts)->toBe(1)->and($result->emails)->toBe(1);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->hasTo($this->user->email)
        && plainSpaces($mail->subjectLine) === 'BIOMUTANT za 132 Kč'
        && plainSpaces($mail->lines[0]['reason']) === 'pod cílovou cenou 200 Kč'
        && $mail->lines[0]['detailUrl'] === route('games.show', 'biomutant'));

    $item = $this->item->fresh();
    expect($item?->notified_amount)->toBe('131.81')
        ->and($item?->notified_currency)->toBe('CZK')
        ->and($item?->notified_at)->not->toBeNull();
});

it('s PS Plus upozorní na měsíční hru Essential jednou, bez PS Plus ne (R35)', function (bool $hasPlus, int $alerts): void {
    $user = watcherOf([CONTROL_RESONANT_ID], ['has_ps_plus' => $hasPlus]);
    // Produkt F1 25 je teď měsíční hra — s PS Plus za 0
    app(ConnectPlayStationProduct::class)->handle(
        $user->watchedGames()->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_F1_25,
    );

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe($alerts);
    if ($hasPlus) {
        Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->hasTo($user->email)
            && $mail->lines[0]['reason'] === __('app.alerts.reason_plus_monthly')
            && plainSpaces($mail->lines[0]['price']) === '0 Kč');

        // Podruhé už ne
        expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
    }
})->with([
    's PS Plus' => [true, 1],
    'bez PS Plus' => [false, 0],
]);

it('stejnou cenu podruhé nepošle, nižší ano', function (): void {
    setTarget($this->item, '200.00');
    app(SendPriceAlerts::class)->handle();
    app(SendPriceAlerts::class)->handle();

    Mail::assertSentCount(1);

    // Poslední upozornění bylo na vyšší cenu — teď je nižší
    $this->item->update(['notified_amount' => '150.00']);
    app(SendPriceAlerts::class)->handle();

    Mail::assertSentCount(2);
});

it('cíl nad cenou neupozorní a stav posledního upozornění smaže', function (): void {
    setTarget($this->item, '100.00');
    $this->item->update(['notified_amount' => '131.81', 'notified_currency' => 'CZK']);

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0)
        ->and($this->item->fresh()?->notified_amount)->toBeNull();
    Mail::assertNothingSent();
});

it('cílovou cenu v jiné měně převede kurzem', function (): void {
    // 6 € = 146,19 Kč > 131,81 Kč
    setTarget($this->item, '6.00', 'EUR');

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
});

it('bez cílové ceny upozorní na historické minimum obchodů s klíči', function (): void {
    PriceLow::query()->where('scope', PriceScope::Keyshops->value)->update(['amount' => '5.41']);

    app(SendPriceAlerts::class)->handle();

    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->lines[0]['reason'] === 'historické minimum');
});

it('historické minimum oficiálních obchodů jen ve slevě', function (): void {
    CurrentPrice::query()->where('shop_key', CurrentPrice::KEYSHOPS_KEY)->delete();
    PriceLow::query()->where('scope', PriceScope::Official->value)->update(['amount' => '24.96']);

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);

    // Bez slevy nejde o výhodnou cenu, i když je na minimu
    WatchlistItem::query()->update(['notified_amount' => null]);
    CurrentPrice::query()->update(['cut' => 0]);
    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
});

it('hra hlídaná jen na PlayStationu nepočítá PC ani klíče', function (): void {
    setTarget($this->item, '200.00');
    $this->item->update(['platform' => 'playstation']);

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
});

it('účet bez kanálu přeskočí a jeho stav nemění', function (): void {
    setTarget($this->item, '200.00');
    $this->user->update(['notify_email' => false]);

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0)
        ->and($this->item->fresh()?->notified_amount)->toBeNull();
});

it('pošle upozornění na zařízení a zrušený odběr smaže', function (): void {
    enablePush();
    setTarget($this->item, '200.00');
    $this->user->update(['notify_email' => false]);
    $this->user->pushSubscriptions()->create([
        ...collect(browserSubscription('https://fcm.googleapis.com/fcm/send/telefon'))->only(['endpoint', 'content_encoding'])->all(),
        'endpoint_hash' => PushSubscription::hashEndpoint('https://fcm.googleapis.com/fcm/send/telefon'),
        'public_key' => browserSubscription()['keys']['p256dh'],
        'auth_token' => browserSubscription()['keys']['auth'],
    ]);
    fakeApis(['fcm.googleapis.com/fcm/send/telefon' => Http::response('', 201)]);

    $result = app(SendPriceAlerts::class)->handle();

    expect($result->pushes)->toBe(1)->and($result->emails)->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/fcm/send/telefon'
        && $request->header('Topic') === ['game-'.$this->item->game_id]
        && str_starts_with($request->header('Authorization')[0], 'vapid t='));
    expect($this->item->fresh()?->notified_amount)->toBe('131.81');

    // Push služba odběr zrušila — smaže se, upozornění zůstane nedoručené
    WatchlistItem::query()->update(['notified_amount' => null]);
    fakeApis(['fcm.googleapis.com/fcm/send/telefon' => Http::response('', 410)]);

    expect(app(SendPriceAlerts::class)->handle()->undelivered)->toBe(1)
        ->and(PushSubscription::query()->count())->toBe(0);
});

it('víc her najednou pošle na zařízení jednou souhrnně', function (): void {
    enablePush();
    config(['games.alerts.max_push_messages' => 0]);
    setTarget($this->item, '200.00');
    $subscription = browserSubscription();
    $this->user->pushSubscriptions()->create([
        'endpoint' => $subscription['endpoint'],
        'endpoint_hash' => PushSubscription::hashEndpoint($subscription['endpoint']),
        'public_key' => $subscription['keys']['p256dh'],
        'auth_token' => $subscription['keys']['auth'],
        'content_encoding' => 'aes128gcm',
    ]);
    fakeApis(['fcm.googleapis.com/*' => Http::response('', 201)]);

    app(SendPriceAlerts::class)->handle();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/')
        && $request->header('Topic') === ['summary']);
});

it('chyba SMTP upozornění neoznačí jako odeslané a cron vrátí 500', function (): void {
    setTarget($this->item, '200.00');
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP neodpovídá'));

    $this->get('/cron/ceny?token=test-cron-token')
        ->assertStatus(500)
        ->assertSeeText('upozornění — her: 1, e-mailů: 0, na zařízení: 0, nedoručeno: 1');

    expect($this->item->fresh()?->notified_amount)->toBeNull();
});

it('cron vypíše souhrn upozornění', function (): void {
    setTarget($this->item, '200.00');

    $this->get('/cron/ceny?token=test-cron-token')
        ->assertOk()
        ->assertSeeText('upozornění — her: 1, e-mailů: 1, na zařízení: 0');
});

it('e-mail se vykreslí s hrou, cenou a odkazem do obchodu', function (): void {
    setTarget($this->item, '200.00');
    app(SendPriceAlerts::class)->handle();

    Mail::assertSent(PriceAlertMail::class, function (PriceAlertMail $mail): bool {
        $html = $mail->render();

        return str_contains($html, 'BIOMUTANT')
            && str_contains($html, '132')
            && str_contains($html, 'https://gg.deals/game/biomutant/')
            && str_contains($html, route('settings.show'));
    });
});

it('PS Plus u účtu počítá cenu pro předplatitele', function (): void {
    fakeApis(['store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US => Http::response(psnFixture('product-kcd2-expansion-us'))]);
    app(ConnectPlayStationProduct::class)->handle($this->item->game()->sole(), 'https://store.playstation.com/cs-cz/product/'.PSN_KCD2_EXPANSION_US);
    $price = CurrentPrice::query()->where('platform', 'playstation')->sole();
    $rates = app(ExchangeRates::class);
    $regular = $rates->convert($price->price(), 'CZK');
    $member = $rates->convert($price->memberPrice() ?? $price->price(), 'CZK');
    expect($member?->cents)->toBeLessThan($regular?->cents ?? 0);

    // Cíl mezi cenou s PS Plus a běžnou cenou, hlídá se jen PlayStation
    $between = Money::fromDecimal((string) intdiv(($member?->cents ?? 0) + ($regular?->cents ?? 0), 2 * Money::SUBUNITS), 'CZK');
    $this->item->update(['platform' => 'playstation', 'target_amount' => $between->toDecimal(), 'target_currency' => 'CZK']);

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);

    User::query()->update(['has_ps_plus' => true]);
    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
});
