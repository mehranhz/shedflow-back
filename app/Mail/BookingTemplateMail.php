<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BookingTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{filename:string,content:string,contentType:string}|null  $icsAttachment
     */
    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public string $textBody,
        public ?string $replyToAddress = null,
        public ?string $idempotencyKey = null,
        public ?array $icsAttachment = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(subject: $this->subjectLine);

        if ($this->replyToAddress) {
            $envelope = $envelope->replyTo($this->replyToAddress);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            text: 'mail.booking-text',
            with: ['textBody' => $this->textBody],
        );
    }

    public function headers(): Headers
    {
        if (! $this->idempotencyKey) {
            return new Headers;
        }

        return new Headers(text: [
            'Idempotency-Key' => $this->idempotencyKey,
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->icsAttachment === null) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $this->icsAttachment['content'],
                $this->icsAttachment['filename'],
            )->withMime($this->icsAttachment['contentType']),
        ];
    }
}
