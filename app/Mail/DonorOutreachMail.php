<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonorOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromEmail
                ? new Address($this->fromEmail, $this->fromName ?: $this->fromEmail)
                : null,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->bodyText)),
        );
    }
}
