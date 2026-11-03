<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

class PaymentReceiptUploaded extends Notification
{
    public function __construct(protected Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        $booking = $this->booking->loadMissing(['customer', 'eventType', 'organization']);
        $invitee = $booking->customer?->name ?? 'An invitee';
        $event = $booking->eventType?->title ?? 'a meeting';

        return sprintf(
            'SchedFlow: %s uploaded a payment receipt for %s. Review it in your dashboard.',
            $invitee,
            $event,
        );
    }
}
