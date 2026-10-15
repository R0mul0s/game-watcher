<?php

/**
 * E-mail s upozorněním na ceny sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Jeden e-mail se všemi hrami, které od minulého e-mailu zlevnily (R26),
 * vyšly (R41) nebo jsou v Game Passu či bundlu (R50, R51) — ze záznamů centra
 * upozornění, ráno nebo hned podle účtu (R53). Odesílá se synchronně — nesmí
 * implementovat ShouldQueue (hosting nemá frontu, R2).
 */
class PriceAlertMail extends Mailable
{
    /**
     * @param  list<array{title: string, price: string, shop: string, reason: string, detailUrl: string, shopUrl: string|null}>  $lines
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly array $lines,
    ) {}

    /**
     * Předmět e-mailu.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /**
     * Šablona e-mailu (Markdown komponenty Laravelu).
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.price-alert', with: [
            'lines' => $this->lines,
            'centerUrl' => route('notifications.index'),
            'settingsUrl' => route('settings.show'),
        ]);
    }
}
