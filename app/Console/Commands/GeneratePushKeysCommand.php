<?php

/**
 * Vygeneruje klíče VAPID pro upozornění na telefon.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Klíče se generují lokálně a vloží do .env na hostingu (artisan tam nejde
 * spustit, R2). Převzato z Počasí.
 */
class GeneratePushKeysCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'games:push-keys';

    /**
     * @var string
     */
    protected $description = 'Vypíše nový pár klíčů VAPID pro upozornění na telefon (Web Push)';

    /**
     * Vypíše nový pár klíčů. Spouští se jednou — po změně klíčů prohlížeče
     * staré odběry odmítnou a upozornění je potřeba na zařízeních zapnout znovu.
     */
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info(__('app.alerts.keys_generated'));
        $this->line('GAMES_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('GAMES_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
