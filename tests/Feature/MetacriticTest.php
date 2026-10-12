<?php

/**
 * Metascore z Metacritic pro hry mimo ITAD a hodnocení hráčů z PS Store (R33).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Metacritic\Actions\RefreshMetascores;
use App\Domain\PlayStation\Actions\ConnectPlayStationProduct;
use App\Enums\MonitoredSource;
use App\Models\Game;
use App\Models\SourceStatus;
use App\Models\StoreListing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    fakeApis();
    $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00', 'Europe/Prague'));
});

/**
 * Byl odeslán požadavek na Metacritic?
 */
function metacriticRequested(): bool
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'metacritic.com'))->isNotEmpty();
}

it('exkluzivitě PS5 bez Metascore v ITAD ho při přidání doplní z Metacritic', function (): void {
    $user = watcherOf([SAROS_ID]);
    $game = Game::query()->where('itad_id', SAROS_ID)->sole();

    expect($game)
        ->metacritic_slug->toBe('saros')
        ->metacritic_score->toBe(87)
        ->metacritic_reviews->toBe(143)
        ->and($game->metascore())->toBe(87);

    $this->actingAs($user)
        ->get('/hry/saros')
        ->assertInertia(fn (Assert $page) => $page
            ->where('game.reviews.0.source', 'Metascore')
            ->where('game.reviews.0.score', 87)
            ->where('game.reviews.0.url', 'https://www.metacritic.com/game/saros/critic-reviews/')
            ->where('game.metacritic.url', 'https://www.metacritic.com/game/saros/'));

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('games.0.metascore', ['score' => 87, 'level' => 'good']));
});

it('hra s Metascore z ITAD Metacritic nevolá a odkaz nenabízí', function (): void {
    $user = watcherOf([BIOMUTANT_ID]);

    expect(metacriticRequested())->toBeFalse();
    $this->actingAs($user)->get('/hry/biomutant')->assertInertia(fn (Assert $page) => $page->where('game.metacritic', null));
});

it('nenalezenou hru označí a znovu ji zkusí až po refresh_days', function (): void {
    // Alan Wake 2 má na Metacritic slug alan-wake-ii — odvozený alan-wake-2 je 404
    watcherOf([ALAN_WAKE_2_ID]);
    $game = Game::query()->where('itad_id', ALAN_WAKE_2_ID)->sole();
    $refresh = app(RefreshMetascores::class);

    expect($game->metacritic_checked_at)->not->toBeNull()
        ->and($game->metacritic_score)->toBeNull()
        ->and($game->metacritic_slug)->toBeNull()
        ->and($refresh->pending())->toHaveCount(0);

    $this->travel((int) config('games.sources.metacritic.refresh_days') + 1)->days();

    expect($refresh->pending()->pluck('id')->all())->toBe([$game->id]);
});

it('odkaz od uživatele připojí stránku s jiným slugem', function (): void {
    $user = watcherOf([ALAN_WAKE_2_ID]);

    $this->actingAs($user)
        ->from('/hry/alan-wake-2')
        ->put('/hry/alan-wake-2/metacritic', ['url' => 'https://www.metacritic.com/game/alan-wake-ii/critic-reviews/?platform=pc'])
        ->assertRedirect('/hry/alan-wake-2')
        ->assertInertiaFlash('success', __('app.metacritic.linked', ['score' => 89]));

    expect(Game::query()->where('itad_id', ALAN_WAKE_2_ID)->sole())
        ->metacritic_slug->toBe('alan-wake-ii')
        ->metacritic_score->toBe(89);
});

it('připojí i hru, kterou kritici ještě nehodnotili', function (): void {
    $user = watcherOf([ALAN_WAKE_2_ID]);

    $this->actingAs($user)
        ->put('/hry/alan-wake-2/metacritic', ['url' => 'metacritic.com/game/intergalactic-the-heretic-prophet/'])
        ->assertInertiaFlash('success', __('app.metacritic.linked_unrated', ['name' => 'Intergalactic: The Heretic Prophet']));

    expect(Game::query()->where('itad_id', ALAN_WAKE_2_ID)->sole())
        ->metacritic_slug->toBe('intergalactic-the-heretic-prophet')
        ->metacritic_score->toBeNull();
});

it('odkaz mimo Metacritic odmítne a nikam nevolá (SSRF)', function (string $url): void {
    $user = watcherOf([ALAN_WAKE_2_ID]);
    fakeApis();

    $this->actingAs($user)
        ->put('/hry/alan-wake-2/metacritic', ['url' => $url])
        ->assertSessionHasErrors(['url' => __('app.metacritic.not_found')]);

    expect(metacriticRequested())->toBeFalse();
})->with([
    'jiný hostitel' => ['https://evil.example.com/game/saros/'],
    'hostitel jako podřetězec' => ['https://www.metacritic.com.evil.example/game/saros/'],
    'jiná sekce' => ['https://www.metacritic.com/movie/saros/'],
]);

it('stránku odvozenou z názvu, která patří jiné hře, nevezme', function (): void {
    // Hra „Control“ by dostala slug control — stránka tam ale patří hře SAROS
    fakeApis(['www.metacritic.com/game/control/' => Http::response(metacriticFixture('saros'))]);
    $game = Game::factory()->create(['title' => 'Control', 'reviews' => []]);

    app(RefreshMetascores::class)->handle(collect([$game]));

    expect($game->fresh())
        ->metacritic_score->toBeNull()
        ->metacritic_slug->toBeNull()
        ->metacritic_checked_at->not->toBeNull();
});

it('stránku bez strukturovaných dat bere jako změnu formátu — nic nepřepíše', function (): void {
    $game = Game::factory()->create(['title' => 'SAROS', 'reviews' => [], 'metacritic_slug' => 'saros', 'metacritic_score' => 87]);
    fakeApis(['www.metacritic.com/*' => Http::response('<html><body>Nový web</body></html>')]);

    [$count, $error] = app(RefreshMetascores::class)->handle(collect([$game]));

    expect($count)->toBe(0)
        ->and($error)->toContain('nemá strukturovaná data hry')
        ->and($game->fresh()?->metacritic_score)->toBe(87);
});

it('cron doplní Metascore, výpadek Metacritic běh neoznačí jako chybný', function (): void {
    $user = user();
    $user->watchlistItems()->create(['game_id' => Game::factory()->create(['title' => 'SAROS', 'reviews' => []])->id]);

    $this->get('/cron/ceny?token=test-cron-token')->assertOk()->assertSeeText('Metascore z Metacritic — her: 1');
    expect(Game::query()->where('title', 'SAROS')->sole()->metacritic_score)->toBe(87);

    // Další hra, Metacritic mimo provoz
    $user->watchlistItems()->create(['game_id' => Game::factory()->create(['title' => 'Returnal', 'reviews' => []])->id]);
    fakeApis(['www.metacritic.com/*' => Http::response('', 503)]);

    $this->get('/cron/ceny?token=test-cron-token')->assertOk()->assertSeeText('Metacritic neodpovídá');
    expect(SourceStatus::query()->where('source', MonitoredSource::Metacritic->value)->sole()->failures_in_row)->toBe(1);
});

it('hodnocení hráčů z PS Store uloží k produktu a ukáže na kartě i detailu', function (): void {
    $user = watcherOf([CONTROL_RESONANT_ID]);
    app(ConnectPlayStationProduct::class)->handle(
        Game::query()->where('itad_id', CONTROL_RESONANT_ID)->sole(),
        'https://store.playstation.com/cs-cz/product/'.PSN_CONTROL_RESONANT,
    );

    expect(StoreListing::query()->sole())
        ->star_rating->toBe('4.76')
        ->star_ratings_count->toBe(8719);

    $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('games.0.playerRating', ['rating' => 4.76, 'count' => 8719]));

    $this->actingAs($user)->get('/hry/control-resonant')->assertInertia(fn (Assert $page) => $page
        ->where('game.playerRatings.0.rating', 4.76)
        ->where('game.playerRatings.0.count', 8719));
});
