<?php

/**
 * Centrum upozornění (R53) — záznamy z cronu, stránka, otevření a označení přečtených,
 * zvonek v hlavičce, výpadky zdrojů a úklid starých záznamů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendPriceAlerts;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Domain\Maintenance\PruneNotifications;
use App\Domain\Monitoring\SourceMonitor;
use App\Domain\Notifications\Actions\RecordSourceOutages;
use App\Domain\Notifications\NotificationCenter;
use App\Enums\MonitoredSource;
use App\Enums\NotificationKind;
use App\Models\SourceStatus;
use App\Models\User;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    Mail::fake();
});

/**
 * Účet s upozorněním na BIOMUTANT pod cílovou cenou v centru, jak ho zapíše cron.
 */
function userWithAlert(): User
{
    $user = watcherOf([BIOMUTANT_ID]);
    WatchlistItem::query()->where('user_id', $user->id)->update(['target_amount' => '200.00', 'target_currency' => 'CZK']);
    app(SendPriceAlerts::class)->handle();

    return $user;
}

/**
 * Stav zdrojů: všechny v pořádku, vyjmenované s výpadkem (poslední úspěch před dvěma dny).
 *
 * @param  list<MonitoredSource>  $down
 */
function sourcesState(array $down = []): void
{
    $monitor = app(SourceMonitor::class);
    foreach (MonitoredSource::cases() as $source) {
        $monitor->succeeded($source);
    }
    foreach ($down as $source) {
        $monitor->failed($source, 'HTTP 503');
        SourceStatus::query()->where('source', $source->value)->update(['last_success_at' => CarbonImmutable::now()->subDays(2)]);
    }
}

it('vyžaduje přihlášení', function (): void {
    $this->get('/upozorneni')->assertRedirect('/prihlaseni');
});

it('ukáže záznamy od nejnovějšího s nepřečtenými', function (): void {
    $user = userWithAlert();
    $this->travel(1)->hour();
    app(NotificationCenter::class)->record($user, NotificationKind::WeeklyDigest, ['games' => ['BIOMUTANT', 'Fable']]);

    $this->actingAs($user)
        ->get('/upozorneni')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications')
            ->has('notifications', 2)
            ->where('notifications.0.kind', 'weekly_digest')
            ->where('notifications.0.title', 'Za týden zlevnily 2 sledované hry')
            ->where('notifications.0.text', 'BIOMUTANT, Fable')
            ->where('notifications.1.kind', 'game_alert')
            ->where('notifications.1.title', fn (string $title): bool => plainSpaces($title) === 'BIOMUTANT za 132 Kč')
            ->where('notifications.1.text', fn (string $text): bool => plainSpaces($text) === 'pod cílovou cenou 200 Kč · obchody s klíči')
            ->where('notifications.1.unread', true)
            ->where('readUrl', '/upozorneni/precteno')
            ->where('retentionDays', 30)
            // Zobrazení seznamu nic neoznačí
            ->where('notificationCenter.unread', 2)
            ->where('notificationCenter.active', true));
});

it('zvonek v hlavičce počítá nepřečtené záznamy', function (): void {
    $user = userWithAlert();

    $this->actingAs($user)
        ->get('/nastaveni')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationCenter.url', '/upozorneni')
            ->where('notificationCenter.unread', 1)
            ->where('notificationCenter.active', false));
});

it('otevření záznamu ho označí jako přečtený a přejde na hru', function (): void {
    $user = userWithAlert();
    /** @var DatabaseNotification $record */
    $record = $user->notifications()->sole();

    $this->actingAs($user)->get('/upozorneni/'.$record->id)->assertRedirect('/hry/biomutant');

    expect($record->fresh()?->read_at)->not->toBeNull();
});

it('cizí záznam neotevře ani neoznačí', function (): void {
    $record = userWithAlert()->notifications()->sole();
    $other = user(['email' => 'other@example.com']);

    $this->actingAs($other)->get('/upozorneni/'.$record->id)->assertNotFound();
    $this->actingAs($other)->post('/upozorneni/precteno', ['ids' => [$record->id]])->assertRedirect();

    expect($record->fresh()?->read_at)->toBeNull();
});

it('označí všechny nepřečtené záznamy jako přečtené', function (): void {
    $user = userWithAlert();
    app(NotificationCenter::class)->record($user, NotificationKind::Sources, ['down' => []]);
    $ids = $user->notifications()->pluck('id')->all();

    $this->actingAs($user)
        ->from('/upozorneni')
        ->post('/upozorneni/precteno', ['ids' => $ids])
        ->assertRedirect('/upozorneni');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('odmítne označení s neplatnými ID', function (): void {
    $this->actingAs(user())
        ->from('/upozorneni')
        ->post('/upozorneni/precteno', ['ids' => ['neni-uuid']])
        ->assertSessionHasErrors(['ids.0' => __('app.validation.notification_ids')]);
});

it('výpadek zdroje zapíše všem účtům jednou, obnovu taky jednou', function (): void {
    $first = user();
    $second = user(['email' => 'second@example.com']);
    $outages = app(RecordSourceOutages::class);

    sourcesState();
    expect($outages->handle())->toBe(0);

    sourcesState([MonitoredSource::Itad]);
    expect($outages->handle())->toBe(2)
        // Pořád stejný výpadek — nic nového
        ->and($outages->handle())->toBe(0);

    $this->travel(4)->hours();
    sourcesState();
    expect($outages->handle())->toBe(2);

    $this->actingAs($second)
        ->get('/upozorneni')
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications', 2)
            ->where('notifications.1.title', 'Výpadek zdrojů cen')
            ->where('notifications.1.text', 'IsThereAnyDeal — oficiální obchody — ceny nemusí být aktuální')
            ->where('notifications.0.title', 'Zdroje cen zase fungují'));

    $record = $first->notifications()->latest()->firstOrFail();
    $this->actingAs($first)->get('/upozorneni/'.$record->id)->assertRedirect('/stav-zdroju');
});

it('částečné zotavení nezapíše nic, nový výpadek ano', function (): void {
    user();
    $outages = app(RecordSourceOutages::class);

    sourcesState([MonitoredSource::Itad, MonitoredSource::GgDeals]);
    expect($outages->handle())->toBe(1);

    sourcesState([MonitoredSource::Itad]);
    expect($outages->handle())->toBe(0);

    sourcesState([MonitoredSource::Itad, MonitoredSource::PlayStation]);
    expect($outages->handle())->toBe(1);
});

it('smaže záznamy starší než retention_days', function (): void {
    $user = userWithAlert();
    $this->travel((int) config('games.notifications.retention_days') + 1)->days();
    app(NotificationCenter::class)->record($user, NotificationKind::Sources, ['down' => []]);

    expect(app(PruneNotifications::class)->handle())->toBe(1)
        ->and($user->notifications()->sole()->type)->toBe(NotificationKind::Sources->value);
});

it('cron vypíše řádek centra upozornění', function (): void {
    $this->get('/cron/ceny?token=test-cron-token')
        ->assertSeeText('centrum upozornění — záznamů o zdrojích: 0, smazáno starých: 0');
});
