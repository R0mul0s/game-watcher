<?php

/**
 * Upozornění na hru, jak je uložené v centru upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Obsah záznamu NotificationKind::GameAlert (R53) — hotové texty v měně účtu
 * z doby upozornění. Z něj se skládá řádek centra, zpráva na zařízení
 * i řádek e-mailu, takže říkají totéž a e-mail ráno ukáže cenu, na kterou
 * upozornění bylo. Texty vytváří AlertPresenter::record().
 */
final readonly class GameAlertRecord
{
    public function __construct(
        public int $gameId,
        // Název hry
        public string $game,
        // Nadpis — „BIOMUTANT za 132 Kč“, „Právě vyšlo: …“
        public string $headline,
        // Proč a kde v jednom řádku — text zprávy na zařízení a řádku centra
        public string $body,
        // Cena („“ u Game Passu a bundlu bez ceny)
        public string $price,
        // Kde nabídka je — „v obchodě Steam“
        public string $shop,
        // Proč upozornění přišlo — „historické minimum“
        public string $reason,
        // Novinka (vydání, Game Pass, bundle) — mění předmět e-mailu
        public bool $news,
        // Detail hry (relativní adresa)
        public string $url,
        public ?string $shopUrl,
    ) {}

    /**
     * Data do sloupce notifications.data.
     *
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'gameId' => $this->gameId,
            'game' => $this->game,
            'headline' => $this->headline,
            'body' => $this->body,
            'price' => $this->price,
            'shop' => $this->shop,
            'reason' => $this->reason,
            'news' => $this->news,
            'url' => $this->url,
            'shopUrl' => $this->shopUrl,
        ];
    }

    /**
     * Záznam z uložených dat; null, když data nemají očekávaný tvar (ruční
     * zásah do databáze, starší podoba záznamu).
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $text = fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;

        $gameId = $data['gameId'] ?? null;
        $game = $text('game');
        $headline = $text('headline');
        $url = $text('url');
        if (! is_int($gameId) || $game === null || $headline === null || $url === null) {
            return null;
        }

        return new self(
            gameId: $gameId,
            game: $game,
            headline: $headline,
            body: $text('body') ?? '',
            price: $text('price') ?? '',
            shop: $text('shop') ?? '',
            reason: $text('reason') ?? '',
            news: ($data['news'] ?? false) === true,
            url: $url,
            shopUrl: $text('shopUrl'),
        );
    }
}
