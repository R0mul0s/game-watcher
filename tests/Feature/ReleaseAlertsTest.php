<?php

/**
 * Hry sledované před vydáním — upozornění na vydání a první slevu, odpočet
 * a filtr vydání (R41).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Alerts\Actions\SendPriceAlerts;
use App\Domain\Catalog\Actions\RefreshGameInfo;
use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Mail\PriceAlertMail;
use App\Models\CurrentPrice;
use App\Models\Game;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00', 'Europe/Prague'));
    app(ImportExchangeRates::class)->handle();
    // BIOMUTANT bez cíle: oficiálně 24,96 € −30 %, klíče 5,41 € = 131,81 Kč —
    // upozornění na cenu nepřijde (není na minimu). Hra tu „vychází“ za týden
    // a uživatel ji sleduje od dřív (stav vydání prázdný).
    $this->user = watcherOf([BIOMUTANT_ID]);
    $this->game = Game::query()->sole();
    $this->game->update(['release_date' => '2026-10-20']);
    $this->item = WatchlistItem::query()->sole();
    $this->item->update(['release_notified_at' => null, 'first_sale_notified_at' => null]);
    Mail::fake();
});

it('před vydáním nic nepošle, po vydání ve slevě upozorní na první slevu jednou', function (): void {
    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Europe/Prague'));

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->lines[0]['reason'] === 'první sleva od vydání'
        && plainSpaces($mail->lines[0]['price']) === '608 Kč');

    // Upozornění pokrylo i vydání — podruhé nic
    $item = $this->item->fresh();
    expect($item?->release_notified_at)->not->toBeNull()
        ->and($item?->first_sale_notified_at)->not->toBeNull()
        ->and($item?->notified_amount)->toBeNull()
        ->and(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
});

it('vydaná hra bez slevy dostane „právě vyšlo“ s nejlevnější nabídkou', function (): void {
    CurrentPrice::query()->update(['cut' => 0]);
    $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00', 'Europe/Prague'));

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => plainSpaces($mail->subjectLine) === 'Právě vyšlo: BIOMUTANT za 132 Kč'
        && $mail->lines[0]['reason'] === 'právě vyšlo');

    $item = $this->item->fresh();
    expect($item?->release_notified_at)->not->toBeNull()
        ->and($item?->first_sale_notified_at)->toBeNull();

    // První sleva přijde zvlášť, až nastane
    CurrentPrice::query()->where('shop_key', '!=', CurrentPrice::KEYSHOPS_KEY)->update(['cut' => 30]);
    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => $mail->lines[0]['reason'] === 'první sleva od vydání');
});

it('vydání starší než release_alert_days už neohlásí', function (): void {
    CurrentPrice::query()->update(['cut' => 0]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Europe/Prague')->addDays((int) config('games.alerts.release_alert_days') + 1));

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(0);
});

it('upozornění na cenu má přednost a pokryje vydání i první slevu', function (): void {
    $this->item->update(['target_amount' => '700.00', 'target_currency' => 'CZK']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Europe/Prague'));

    expect(app(SendPriceAlerts::class)->handle()->alerts)->toBe(1);
    Mail::assertSent(PriceAlertMail::class, fn (PriceAlertMail $mail): bool => str_starts_with($mail->lines[0]['reason'], 'pod cílovou cenou'));

    $item = $this->item->fresh();
    expect($item?->notified_amount)->toBe('131.81')
        ->and($item?->release_notified_at)->not->toBeNull()
        // Nejlevnější byly klíče — ty slevu neuvádějí, první sleva tak dál čeká
        ->and($item?->first_sale_notified_at)->toBeNull();
});

it('hra sledovaná až po vydání na vydání ani první slevu neupozorní', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID]);
    $item = $user->watchlistItems()->sole();

    expect($item->release_notified_at)->not->toBeNull()
        ->and($item->first_sale_notified_at)->not->toBeNull();
});

it('karta a detail ukážou odpočet, filtr vydání oddělí nevydané hry', function (): void {
    watcherOf([CONTROL_RESONANT_ID], ['email' => 'second@example.com']);
    $this->user->watchlistItems()->create(['game_id' => Game::query()->where('itad_id', CONTROL_RESONANT_ID)->value('id')]);

    $this->actingAs($this->user)
        ->get('/?release=upcoming')
        ->assertInertia(fn (Assert $page) => $page
            ->has('games', 1)
            ->where('games.0.title', 'BIOMUTANT')
            ->where('games.0.releaseInDays', 7)
            ->where('filters.release', 'upcoming')
            ->where('filters.active', true));

    $this->actingAs($this->user)
        ->get('/?release=released')
        ->assertInertia(fn (Assert $page) => $page->has('games', 1)->where('games.0.releaseInDays', null));

    $this->actingAs($this->user)
        ->get('/hry/biomutant')
        ->assertInertia(fn (Assert $page) => $page->where('game.releaseInDays', 7));
});

it('nevydané hry obnovuje z ITAD častěji', function (): void {
    $refresh = app(RefreshGameInfo::class);
    $this->game->update(['info_refreshed_at' => CarbonImmutable::now()->subDays((int) config('games.catalog.info_refresh_days_upcoming') + 1)]);

    expect($refresh->pending()->pluck('id')->all())->toBe([$this->game->id]);

    // Vydaná hra se stejně starým snímkem počká na info_refresh_days
    $this->game->update(['release_date' => '2026-10-01']);
    expect($refresh->pending())->toHaveCount(0);
});
