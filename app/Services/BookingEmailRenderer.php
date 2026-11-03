<?php

namespace App\Services;

use App\Enums\EmailTemplate;
use App\Models\Booking;
use App\Support\BookingWhenFormatter;
use App\Support\Features;
use App\Support\ManageBookingUrl;
use App\Support\PortalBookingUrl;

final class BookingEmailRenderer
{
    /**
     * @param  array<string, string>  $actionTokens
     * @return array{subject:string,html:string,text:string}
     */
    public function render(Booking $booking, EmailTemplate $template, array $actionTokens = []): array
    {
        $booking->loadMissing(['customer', 'eventType', 'host', 'organization']);
        $props = $this->props($booking, $actionTokens);

        return match ($template) {
            EmailTemplate::BookingConfirmedHost => $this->confirmedHost($props),
            EmailTemplate::BookingConfirmedInvitee => $this->confirmedInvitee($props),
            EmailTemplate::BookingPendingHost => $this->pendingHost($props),
            EmailTemplate::BookingCancelledHost => $this->cancelledHost($props),
            EmailTemplate::BookingCancelledInvitee => $this->cancelledInvitee($props),
            EmailTemplate::BookingRescheduledHost => $this->rescheduledHost($props),
            EmailTemplate::BookingRescheduledInvitee => $this->rescheduledInvitee($props),
            EmailTemplate::Reminder24hHost => $this->reminderHost($props, '24 hours'),
            EmailTemplate::Reminder24hInvitee => $this->reminderInvitee($props, '24 hours'),
            EmailTemplate::Reminder1hHost => $this->reminderHost($props, '1 hour'),
            EmailTemplate::Reminder1hInvitee => $this->reminderInvitee($props, '1 hour'),
            EmailTemplate::Reminder24h, EmailTemplate::Reminder1h => throw new \InvalidArgumentException('Use host/invitee reminder templates'),
        };
    }

    /**
     * @param  array<string, string>  $actionTokens
     * @return array{
     *   eventTitle:string,
     *   whenLabel:string,
     *   locationLabel:?string,
     *   inviteeName:string,
     *   inviteeEmail:string,
     *   hostName:string,
     *   manageUrl:?string,
     *   portalUrl:?string,
     *   reason:?string
     * }
     */
    private function props(Booking $booking, array $actionTokens): array
    {
        $manageToken = $actionTokens['manage'] ?? null;
        $manageUrl = $manageToken
            ? ManageBookingUrl::forBooking($booking, $manageToken)
            : null;
        $portalUrl = Features::customerPortalEnabled($booking->organization)
            ? PortalBookingUrl::forBooking($booking)
            : null;

        $hostName = trim((string) ($booking->host?->name ?? ''));
        if ($hostName === '') {
            $hostName = 'Host';
        }

        return [
            'eventTitle' => $booking->eventType?->title ?? 'Meeting',
            'whenLabel' => BookingWhenFormatter::label($booking->start_at, $booking->end_at, $booking->timezone),
            'locationLabel' => $booking->location_value,
            'inviteeName' => $booking->customer?->name ?? 'Guest',
            'inviteeEmail' => $booking->customer?->email ?? '',
            'hostName' => $hostName,
            'manageUrl' => $manageUrl,
            'portalUrl' => $portalUrl,
            'reason' => $booking->cancellation_reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function confirmedHost(array $props): array
    {
        $subject = 'Confirmed: '.$props['eventTitle'];
        $text = implode("\n", array_filter([
            'Booking confirmed',
            "You have a confirmed booking for {$props['eventTitle']}.",
            "When: {$props['whenLabel']}",
            $props['locationLabel'] ? "Where: {$props['locationLabel']}" : null,
            "Invitee: {$props['inviteeName']} <{$props['inviteeEmail']}>",
            $props['manageUrl'] ? "Manage: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "You have a confirmed booking for {$props['eventTitle']}.",
                "When: {$props['whenLabel']}",
                $props['locationLabel'] ? "Where: {$props['locationLabel']}" : null,
                "Invitee: {$props['inviteeName']} &lt;{$props['inviteeEmail']}&gt;",
                $props['manageUrl'] ? '<a href="'.e($props['manageUrl']).'">View booking</a>' : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function confirmedInvitee(array $props): array
    {
        $subject = "You're booked: {$props['eventTitle']}";
        $text = implode("\n", array_filter([
            "You're confirmed for {$props['eventTitle']} with {$props['hostName']}.",
            "When: {$props['whenLabel']}",
            $props['locationLabel'] ? "Where: {$props['locationLabel']}" : null,
            $props['portalUrl'] ? "Manage in SchedFlow: {$props['portalUrl']}" : null,
            $props['manageUrl'] ? "Manage without signing in: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs(array_filter([
                "You're confirmed for {$props['eventTitle']} with {$props['hostName']}.",
                "When: {$props['whenLabel']}",
                $props['locationLabel'] ? "Where: {$props['locationLabel']}" : null,
                $props['portalUrl']
                    ? '<a href="'.e($props['portalUrl']).'">Manage in SchedFlow</a>'
                    : null,
                $props['manageUrl']
                    ? '<a href="'.e($props['manageUrl']).'">Manage without signing in</a>'
                    : null,
            ])),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function pendingHost(array $props): array
    {
        $subject = "Confirm booking: {$props['eventTitle']}";
        $text = implode("\n", array_filter([
            'A booking is awaiting your confirmation.',
            "{$props['inviteeName']} requested {$props['eventTitle']}.",
            "When: {$props['whenLabel']}",
            $props['manageUrl'] ? "Review: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                'A booking is awaiting your confirmation.',
                "{$props['inviteeName']} requested {$props['eventTitle']}.",
                "When: {$props['whenLabel']}",
                $props['manageUrl'] ? '<a href="'.e($props['manageUrl']).'">Review booking</a>' : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function cancelledHost(array $props): array
    {
        $subject = "Cancelled: {$props['eventTitle']}";
        $reasonLine = $props['reason'] ? "Reason: {$props['reason']}" : null;
        $text = implode("\n", array_filter([
            "The booking for {$props['eventTitle']} was cancelled.",
            $reasonLine,
            "When: {$props['whenLabel']}",
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "The booking for {$props['eventTitle']} was cancelled.",
                $reasonLine,
                "When: {$props['whenLabel']}",
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function cancelledInvitee(array $props): array
    {
        $subject = "Cancelled: {$props['eventTitle']}";
        $reasonLine = $props['reason'] ? "Reason: {$props['reason']}" : null;
        $text = implode("\n", array_filter([
            "Your booking for {$props['eventTitle']} was cancelled.",
            $reasonLine,
            "When: {$props['whenLabel']}",
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "Your booking for {$props['eventTitle']} was cancelled.",
                $reasonLine,
                "When: {$props['whenLabel']}",
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function rescheduledHost(array $props): array
    {
        $subject = "Rescheduled: {$props['eventTitle']}";
        $text = implode("\n", array_filter([
            "A booking for {$props['eventTitle']} was rescheduled.",
            "New time: {$props['whenLabel']}",
            $props['manageUrl'] ? "Manage: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "A booking for {$props['eventTitle']} was rescheduled.",
                "New time: {$props['whenLabel']}",
                $props['manageUrl'] ? '<a href="'.e($props['manageUrl']).'">View booking</a>' : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function rescheduledInvitee(array $props): array
    {
        $subject = "New time: {$props['eventTitle']}";
        $text = implode("\n", array_filter([
            "Your booking for {$props['eventTitle']} was moved.",
            "New time: {$props['whenLabel']}",
            $props['manageUrl'] ? "Manage: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "Your booking for {$props['eventTitle']} was moved.",
                "New time: {$props['whenLabel']}",
                $props['manageUrl'] ? '<a href="'.e($props['manageUrl']).'">Manage booking</a>' : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function reminderHost(array $props, string $inLabel): array
    {
        $subject = "Reminder: {$props['eventTitle']} in {$inLabel}";
        $text = implode("\n", array_filter([
            "This is a reminder for {$props['eventTitle']}.",
            "When: {$props['whenLabel']}",
            "Invitee: {$props['inviteeName']} <{$props['inviteeEmail']}>",
            $props['manageUrl'] ? "Manage: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "This is a reminder for {$props['eventTitle']}.",
                "When: {$props['whenLabel']}",
                "Invitee: {$props['inviteeName']} &lt;{$props['inviteeEmail']}&gt;",
                $props['manageUrl'] ? '<a href="'.e($props['manageUrl']).'">View booking</a>' : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{subject:string,html:string,text:string}
     */
    private function reminderInvitee(array $props, string $inLabel): array
    {
        $subject = "Reminder: {$props['eventTitle']} in {$inLabel}";
        $text = implode("\n", array_filter([
            "This is a reminder for {$props['eventTitle']} with {$props['hostName']}.",
            "When: {$props['whenLabel']}",
            $props['portalUrl'] ? "Manage in SchedFlow: {$props['portalUrl']}" : null,
            $props['manageUrl'] ? "Manage without signing in: {$props['manageUrl']}" : null,
        ]));

        return [
            'subject' => $subject,
            'html' => $this->htmlParagraphs([
                "This is a reminder for {$props['eventTitle']} with {$props['hostName']}.",
                "When: {$props['whenLabel']}",
                $props['portalUrl']
                    ? '<a href="'.e($props['portalUrl']).'">Manage in SchedFlow</a>'
                    : null,
                $props['manageUrl']
                    ? '<a href="'.e($props['manageUrl']).'">Manage without signing in</a>'
                    : null,
            ]),
            'text' => $text,
        ];
    }

    /**
     * @param  array<string|null>  $lines
     */
    private function htmlParagraphs(array $lines): string
    {
        $body = collect($lines)
            ->filter()
            ->map(fn (string $line) => '<p>'.$line.'</p>')
            ->implode('');

        return '<!DOCTYPE html><html><body>'.$body.'</body></html>';
    }
}
