<?php

/**
 * Odpojení produktu obchodu od hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\PlayStation\Actions;

use App\Domain\Pricing\StorePrices;
use App\Models\StoreListing;
use Illuminate\Support\Facades\DB;

/**
 * Smaže propojení a jeho aktuální cenu i denní historii — cena patřila jen tomuhle
 * propojení (klíč obchodu psn-<id>), nové propojení by dostalo jiný klíč.
 */
final class DisconnectStoreListing
{
    private const HISTORY_TABLE = 'price_history';

    public function __construct(private readonly StorePrices $storePrices) {}

    /**
     * Odpojí produkt.
     */
    public function handle(StoreListing $listing): void
    {
        DB::transaction(function () use ($listing): void {
            $this->storePrices->forgetListing($listing);
            DB::table(self::HISTORY_TABLE)
                ->where('game_id', $listing->game_id)
                ->where('shop_key', $listing->shopKey())
                ->delete();
            $listing->delete();
        });
    }
}
