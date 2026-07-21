<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonorOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{path?: string|null, name?: string|null, mime?: string|null}|null  $attachment
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public ?array $attachment = null,
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

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->attachment['path'] ?? null;
        if (! filled($path) || ! is_readable($path)) {
            return [];
        }

        $attachment = Attachment::fromPath($path);

        if (filled($this->attachment['name'] ?? null)) {
            $attachment = $attachment->as($this->attachment['name']);
        }

        if (filled($this->attachment['mime'] ?? null)) {
            $attachment = $attachment->withMime($this->attachment['mime']);
        }

        return [$attachment];
    }
}
