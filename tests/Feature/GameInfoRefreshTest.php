<?php

/**
 * Obnova údajů sledovaných her z ITAD v cronu (R34).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Catalog\Actions\RefreshGameInfo;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 18:00', 'Europe/Prague'));
});

/**
 * Snímek údajů hry zestárne o víc než info_refresh_days.
 */
function ageGameInfo(Game $game, array $attributes = []): void
{
    $game->update([
        ...$attributes,
        'info_refreshed_at' => CarbonImmutable::now()->subDays((int) config('games.catalog.info_refresh_days') + 1),
    ]);
}

it('cron obnoví zestárlé údaje — hodnocení i datum vydání', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();
    // Snímek z doby před vydáním: bez hodnocení a data vydání
    ageGameInfo($game, ['reviews' => [], 'release_date' => null]);
    fakeApis();

    $this->get('/cron/ceny?token=test-cron-token')->assertOk()->assertSeeText('údaje her z ITAD — obnoveno: 1');

    expect($game->fresh())
        ->metascore()->toBe(66)
        ->release_date->not->toBeNull()
        ->info_refreshed_at->greaterThan(CarbonImmutable::now()->subMinute())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/games/info/v2'));
});

it('čerstvé údaje nestahuje, starých bere nejvýš info_refresh_per_run', function (): void {
    watcherOf([BIOMUTANT_ID, CONTROL_RESONANT_ID, ALAN_WAKE_2_ID]);
    config(['games.catalog.info_refresh_per_run' => 2]);
    $refresh = app(RefreshGameInfo::class);

    expect($refresh->pending())->toHaveCount(0);

    Game::query()->get()->each(fn (Game $game) => ageGameInfo($game));

    expect($refresh->pending())->toHaveCount(2);
});

it('údaje, které doplnil PS Store, prázdná hodnota z ITAD nepřepíše (R22)', function (): void {
    watcherOf([SAROS_ID]);
    $game = Game::query()->sole();
    app(ConnectPlayStationProduct::class)->handle($game, 'https://store.playstation.com/cs-cz/product/'.PSN_SAROS);
    $filled = $game->fresh();
    ageGameInfo($game);

    app(RefreshGameInfo::class)->handle(collect([$game->fresh()]));

    // ITAD má Saros jen jako zástupný záznam bez obrázků, data vydání a vydavatele
    expect($game->fresh())
        ->boxart_url->toBe($filled?->boxart_url)->not->toBeNull()
        ->banner_url->toBe($filled?->banner_url)
        ->release_date->toEqual($filled?->release_date)
        ->publishers->toBe($filled?->publishers);
});

it('výpadek ITAD obnovu ukončí a hra zůstane na řadě', function (): void {
    watcherOf([BIOMUTANT_ID]);
    $game = Game::query()->sole();
    ageGameInfo($game);
    fakeApis(['api.isthereanydeal.com/games/info/v2*' => apiError(503)]);

    [$count, $error] = app(RefreshGameInfo::class)->handle(collect([$game]));

    expect($count)->toBe(0)
        ->and($error)->toBe('HTTP 503')
        ->and(app(RefreshGameInfo::class)->pending()->pluck('id')->all())->toBe([$game->id]);
});
