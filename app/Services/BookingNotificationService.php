<?php

namespace App\Services;

use App\Enums\EmailTemplate;
use App\Models\Booking;
use App\Support\BookingIcs;

class BookingNotificationService
{
    public function __construct(
        private readonly BookingEmailRenderer $renderer,
        private readonly IdempotentBookingMailer $mailer,
        private readonly BookingReminderService $reminders,
    ) {}

    /**
     * @param  array<string, string>  $actionTokens
     */
    public function sendConfirmed(Booking $booking, array $actionTokens = []): void
    {
        $booking->loadMissing(['customer', 'eventType', 'host', 'organization']);
        $sequence = $booking->rescheduled_from_id ? 1 : 0;
        $ics = $this->icsAttachment($booking, 'REQUEST', $sequence);

        $this->deliverPair(
            $booking,
            $actionTokens,
            EmailTemplate::BookingConfirmedHost,
            EmailTemplate::BookingConfirmedInvitee,
            $ics,
            $ics,
        );

        $this->reminders->scheduleForBooking($booking);
    }

    /**
     * @param  array<string, string>  $actionTokens
     */
    public function sendPendingHost(Booking $booking, array $actionTokens = []): void
    {
        $booking->loadMissing(['customer', 'eventType', 'host', 'organization']);
        $hostEmail = $this->hostEmail($booking);
        if (! $hostEmail) {
            return;
        }

        $rendered = $this->renderer->render($booking, EmailTemplate::BookingPendingHost, $actionTokens);
        $this->mailer->deliver(
            organizationId: $booking->organization_id,
            bookingId: $booking->id,
            template: EmailTemplate::BookingPendingHost,
            to: $hostEmail,
            replyTo: $booking->customer?->email,
            subject: $rendered['subject'],
            html: $rendered['html'],
            text: $rendered['text'],
            idempotencyKey: "{$booking->id}:".EmailTemplate::BookingPendingHost->value,
        );
    }

    /**
     * @param  array<string, string>  $actionTokens
     */
    public function sendCancelled(Booking $booking, array $actionTokens = []): void
    {
        $booking->loadMissing(['customer', 'eventType', 'host', 'organization']);
        $ics = $this->icsAttachment($booking, 'CANCEL', 1);

        $this->deliverPair(
            $booking,
            $actionTokens,
            EmailTemplate::BookingCancelledHost,
            EmailTemplate::BookingCancelledInvitee,
            $ics,
            $ics,
        );
    }

    /**
     * @param  array<string, string>  $actionTokens
     */
    public function sendRescheduled(Booking $booking, array $actionTokens = []): void
    {
        $booking->loadMissing(['customer', 'eventType', 'host', 'organization']);
        $ics = $this->icsAttachment($booking, 'REQUEST', 1);

        $this->deliverPair(
            $booking,
            $actionTokens,
            EmailTemplate::BookingRescheduledHost,
            EmailTemplate::BookingRescheduledInvitee,
            $ics,
            $ics,
        );
    }

    /**
     * @param  array{filename:string,content:string,contentType:string}|null  $hostIcs
     * @param  array{filename:string,content:string,contentType:string}|null  $inviteeIcs
     * @param  array<string, string>  $actionTokens
     */
    private function deliverPair(
        Booking $booking,
        array $actionTokens,
        EmailTemplate $hostTemplate,
        EmailTemplate $inviteeTemplate,
        ?array $hostIcs,
        ?array $inviteeIcs,
    ): void {
        $hostEmail = $this->hostEmail($booking);
        $inviteeEmail = strtolower(trim((string) ($booking->customer?->email ?? '')));

        if ($hostEmail) {
            $rendered = $this->renderer->render($booking, $hostTemplate, $actionTokens);
            $this->mailer->deliver(
                organizationId: $booking->organization_id,
                bookingId: $booking->id,
                template: $hostTemplate,
                to: $hostEmail,
                replyTo: $inviteeEmail ?: null,
                subject: $rendered['subject'],
                html: $rendered['html'],
                text: $rendered['text'],
                idempotencyKey: "{$booking->id}:{$hostTemplate->value}",
                icsAttachment: $hostIcs,
            );
        }

        if ($inviteeEmail !== '') {
            $rendered = $this->renderer->render($booking, $inviteeTemplate, $actionTokens);
            $this->mailer->deliver(
                organizationId: $booking->organization_id,
                bookingId: $booking->id,
                template: $inviteeTemplate,
                to: $inviteeEmail,
                replyTo: $hostEmail,
                subject: $rendered['subject'],
                html: $rendered['html'],
                text: $rendered['text'],
                idempotencyKey: "{$booking->id}:{$inviteeTemplate->value}",
                icsAttachment: $inviteeIcs,
            );
        }
    }

    /**
     * @return array{filename:string,content:string,contentType:string}
     */
    private function icsAttachment(Booking $booking, string $method, int $sequence): array
    {
        $organizerEmail = $this->hostEmail($booking) ?? $this->organizerFallback();
        $content = BookingIcs::build(
            bookingUid: $booking->uid,
            method: $method,
            sequence: $sequence,
            summary: $booking->eventType?->title ?? 'Meeting',
            startAt: $booking->start_at,
            endAt: $booking->end_at,
            organizerEmail: $organizerEmail,
            organizerName: $booking->host?->name,
            attendeeEmail: $booking->customer?->email ?? 'guest@example.com',
            attendeeName: $booking->customer?->name,
            location: $booking->location_value,
        );

        return [
            'filename' => 'invite.ics',
            'content' => $content,
            'contentType' => 'text/calendar; charset=utf-8',
        ];
    }

    private function hostEmail(Booking $booking): ?string
    {
        $settings = $booking->organization?->settings ?? [];
        $fromSettings = data_get($settings, 'notifications.hostEmail')
            ?? data_get($settings, 'hostEmail');

        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return strtolower(trim($fromSettings));
        }

        return null;
    }

    private function organizerFallback(): string
    {
        $from = config('mail.from.address');

        return is_string($from) && $from !== '' ? $from : 'notifications@mail.schedflow.com';
    }
}
