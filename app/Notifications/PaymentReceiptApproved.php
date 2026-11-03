<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

class PaymentReceiptApproved extends Notification
{
    public function __construct(
        protected Booking $booking,
        protected string $manageUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        $booking = $this->booking->loadMissing(['eventType']);
        $event = $booking->eventType?->title ?? 'your meeting';

        return sprintf(
            'SchedFlow: Your payment receipt was approved. %s is confirmed. Manage: %s',
            $event,
            $this->manageUrl,
        );
    }
}
