<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplate;
use App\Jobs\SendBookingReminderJob;
use App\Models\Booking;
use App\Support\ReminderPlanner;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class BookingReminderService
{
    public function __construct(
        private readonly BookingEmailRenderer $renderer,
        private readonly IdempotentBookingMailer $mailer,
    ) {}

    /**
     * @return list<EmailTemplate>
     */
    public function scheduleForBooking(Booking $booking, ?CarbonInterface $now = null): array
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return [];
        }

        $now = CarbonImmutable::instance($now ?? now());
        $startAt = CarbonImmutable::instance($booking->start_at);
        $planned = ReminderPlanner::plan($booking->id, $startAt, $now);
        $templates = [];

        foreach ($planned as $item) {
            $delay = $now->diffInSeconds($item['runAt'], false);
            $job = new SendBookingReminderJob(
                bookingId: $booking->id,
                template: $item['template'],
                startAtIso: $item['startAtIso'],
            );

            if ($delay <= 0) {
                dispatch($job);
            } else {
                dispatch($job)->delay($item['runAt']);
            }

            $templates[] = $item['template'];
        }

        return $templates;
    }

    public function sendIfDue(string $bookingId, EmailTemplate $template, string $startAtIso): void
    {
        $booking = Booking::query()
            ->with(['customer', 'eventType', 'host', 'organization'])
            ->find($bookingId);

        if (! $booking) {
            Log::debug("reminder skip missing booking={$bookingId}");

            return;
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            Log::debug("reminder no-op status={$booking->status->value} booking={$bookingId}");

            return;
        }

        $expectedStart = CarbonImmutable::parse($startAtIso)->utc()->startOfSecond();
        $actualStart = CarbonImmutable::instance($booking->start_at)->utc()->startOfSecond();
        if (! $expectedStart->equalTo($actualStart)) {
            Log::debug("reminder no-op startAt changed booking={$bookingId}");

            return;
        }

        $this->deliverReminderPair($booking, $template);
    }

    private function deliverReminderPair(Booking $booking, EmailTemplate $windowTemplate): void
    {
        $hostTemplate = match ($windowTemplate) {
            EmailTemplate::Reminder24h => EmailTemplate::Reminder24hHost,
            EmailTemplate::Reminder1h => EmailTemplate::Reminder1hHost,
            default => null,
        };
        $inviteeTemplate = match ($windowTemplate) {
            EmailTemplate::Reminder24h => EmailTemplate::Reminder24hInvitee,
            EmailTemplate::Reminder1h => EmailTemplate::Reminder1hInvitee,
            default => null,
        };

        if (! $hostTemplate || ! $inviteeTemplate) {
            return;
        }

        $hostEmail = $this->hostEmail($booking);
        $inviteeEmail = strtolower(trim((string) ($booking->customer?->email ?? '')));

        if ($hostEmail) {
            $rendered = $this->renderer->render($booking, $hostTemplate);
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
            );
        }

        if ($inviteeEmail !== '') {
            $rendered = $this->renderer->render($booking, $inviteeTemplate);
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
            );
        }
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
}
