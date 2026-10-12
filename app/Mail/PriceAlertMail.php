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
 * Jeden e-mail za běh cronu se všemi hrami, které zlevnily (R26). Odesílá
 * se synchronně — nesmí implementovat ShouldQueue (hosting nemá frontu, R2).
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
            'dashboardUrl' => route('dashboard'),
            'settingsUrl' => route('settings.show'),
        ]);
    }
}
