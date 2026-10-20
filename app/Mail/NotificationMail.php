<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One generic HTML mail for the inventory notifications; the wrapper
 * mirrors emailWrap_() from Notify.gs.
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $mailSubject  full subject line, e.g. "[ENT Inventory] Borrow Request Approved — Router"
     * @param  string  $introHtml  short paragraph(s) shown under the heading
     * @param  string  $tableHtml  detail table (or empty string)
     * @param  string  $footerHtml  closing paragraph(s) (or empty string)
     */
    public function __construct(
        string $mailSubject,
        public readonly string $heading,
        public readonly string $introHtml,
        public readonly string $tableHtml,
        public readonly string $footerHtml,
    ) {
        $this->subject = $mailSubject;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'heading' => $this->heading,
                'introHtml' => $this->introHtml,
                'tableHtml' => $this->tableHtml,
                'footerHtml' => $this->footerHtml,
            ],
        );
    }
}
