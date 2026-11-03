<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerClaimMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $claimUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Link your bookings to your Shedflow account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-claim',
            text: 'mail.customer-claim-text',
            with: ['claimUrl' => $this->claimUrl],
        );
    }
}
