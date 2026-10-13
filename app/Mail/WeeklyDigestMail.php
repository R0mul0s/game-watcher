<?php

/**
 * Týdenní souhrn slev sledovaných her.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Jeden e-mail týdně s hrami, které za týden zlevnily (R36). Odesílá se
 * synchronně — nesmí implementovat ShouldQueue (hosting nemá frontu, R2).
 */
class WeeklyDigestMail extends Mailable
{
    /**
     * @param  list<array{title: string, price: string, previous: string, drop: int, detailUrl: string}>  $lines
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
        return new Content(markdown: 'mail.weekly-digest', with: [
            'lines' => $this->lines,
            'dashboardUrl' => route('dashboard'),
            'settingsUrl' => route('settings.show'),
        ]);
    }
}
