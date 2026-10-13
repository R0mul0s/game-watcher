<?php

/**
 * Připojení hry na HowLongToBeat odkazem od uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Hltb\Actions;

use App\Domain\Hltb\Exceptions\HltbGameNotFound;
use App\Domain\Hltb\Exceptions\HltbUnavailable;
use App\Domain\Hltb\HltbClient;
use App\Domain\Hltb\HltbGame;
use App\Domain\Hltb\HltbUrl;
use App\Models\Game;

/**
 * Když hledání podle názvu hru nenajde nebo najde jinou (R44), uživatel vloží
 * odkaz na detailu hry — délky se pak obnovují z té stránky.
 */
final class LinkHltb
{
    public function __construct(
        private readonly HltbClient $client,
        private readonly RefreshPlaytimes $refreshPlaytimes,
    ) {}

    /**
     * Připojí hru a uloží její délky.
     *
     * @throws HltbGameNotFound
     * @throws HltbUnavailable
     */
    public function handle(Game $game, string $url): HltbGame
    {
        $id = HltbUrl::parse($url) ?? throw new HltbGameNotFound($url);
        $hltb = $this->client->game($id) ?? throw new HltbGameNotFound($url);

        $this->refreshPlaytimes->store($game, $hltb);

        return $hltb;
    }
}
