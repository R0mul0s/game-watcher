<?php

/**
 * Stránka Objevit — slevy z ITAD podle štítků sledovaných her (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

use App\Domain\Currency\Actions\ImportExchangeRates;
use App\Models\Game;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** Hry ze zachyceného seznamu slev (tests/Fixtures/itad/deals.json). */
const DEAL_SEKIRO_ID = '018d937f-5599-70de-b7b0-96f7e67684ff';
const DEAL_TUNIC_ID = '018d937f-0186-717a-8898-b39f09dd8206';

beforeEach(function (): void {
    fakeApis();
    app(ImportExchangeRates::class)->handle();
    // BIOMUTANT má štítky Open World, Action, Singleplayer, RPG, Adventure
    $this->user = watcherOf([BIOMUTANT_ID]);
});

/**
 * Požadavky na seznam slev ITAD.
 *
 * @return list<Request>
 */
function dealRequests(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/deals/v2'))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

it('ukáže slevy v měně účtu s nabídkou štítků a odkazem do obchodu', function (): void {
    $this->actingAs($this->user)
        ->get('/objevit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Discover')
            ->has('deals', 6)
            ->where('deals.0.title', 'Sekiro™: Shadows Die Twice - GOTY Edition')
            ->where('deals.0.shop', 'Steam')
            ->where('deals.0.cut', 50)
            ->where('deals.0.flag', 'low')
            ->where('deals.0.price.currency', 'CZK')
            ->where('deals.0.original', ['amount' => 29.99, 'currency' => 'EUR'])
            ->where('deals.0.url', fn (string $url): bool => str_starts_with($url, 'https://itad.link/'))
            ->where('deals.0.detailUrl', null)
            ->where('unavailable', false)
            ->where('filters', ['tags' => [], 'sort' => 'popular', 'lows' => true])
            // Předvolené štítky pro hraní ve dvou, pak štítky sledovaných her s počtem
            ->where('tags.0', ['name' => 'Local Co-Op', 'count' => null])
            ->where('tags', fn ($tags): bool => collect($tags)->contains(['name' => 'RPG', 'count' => 1])));
});

it('vynechá hry, které uživatel sleduje, a u her z katalogu odkáže na detail', function (): void {
    $sekiro = Game::factory()->create(['itad_id' => DEAL_SEKIRO_ID, 'slug' => 'sekiro']);
    $this->user->watchlistItems()->create(['game_id' => $sekiro->id]);
    Game::factory()->create(['itad_id' => DEAL_TUNIC_ID, 'slug' => 'tunic']);

    $this->actingAs($this->user)
        ->get('/objevit')
        ->assertInertia(fn (Assert $page) => $page
            ->has('deals', 5)
            ->where('deals', fn ($deals): bool => ! collect($deals)->contains('itadId', DEAL_SEKIRO_ID)
                && collect($deals)->firstWhere('itadId', DEAL_TUNIC_ID)['detailUrl'] === '/hry/tunic'));
});

it('filtry pošle ITAD jako JSON a výsledek drží v cache', function (): void {
    $this->actingAs($this->user)->get('/objevit?tags[]=RPG&tags[]=Local%20Co-Op&sort=cut')->assertOk();
    // Stejné štítky v jiném pořadí jsou týž dotaz
    $this->actingAs($this->user)->get('/objevit?tags[]=Local%20Co-Op&tags[]=RPG&sort=cut')->assertOk();

    $requests = dealRequests();
    expect($requests)->toHaveCount(1)
        ->and($requests[0]['sort'])->toBe('-cut')
        ->and($requests[0]['country'])->toBe('CZ')
        ->and(json_decode($requests[0]['filter'], true))->toBe(['type' => [1], 'flag' => 'H', 'tagsUnion' => ['RPG', 'Local Co-Op']]);

    // Bez „jen minima“ jiný dotaz bez značky
    $this->actingAs($this->user)->get('/objevit?lows=0')->assertInertia(fn (Assert $page) => $page->where('filters.lows', false));
    expect(json_decode(dealRequests()[1]['filter'], true))->toBe(['type' => [1]]);
});

it('neplatné filtry v adrese ignoruje', function (): void {
    $this->actingAs($this->user)
        ->get('/objevit?sort=nesmysl&lows=asi&tags=RPG')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['tags' => [], 'sort' => 'popular', 'lows' => true]));
});

it('výpadek ITAD ukáže jako hlášku', function (): void {
    fakeApis(['api.isthereanydeal.com/deals/v2*' => apiError(503)]);

    $this->actingAs($this->user)
        ->get('/objevit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('unavailable', true)->has('deals', 0));
});

it('je v hlavní navigaci a bez přihlášení přesměruje', function (): void {
    $this->get('/objevit')->assertRedirect('/prihlaseni');

    $this->actingAs($this->user)
        ->get('/objevit')
        ->assertInertia(fn (Assert $page) => $page->where('navigation.1', ['url' => '/objevit', 'label' => 'nav.discover', 'active' => true]));
});
