<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

class PaymentReceiptRejected extends Notification
{
    public function __construct(
        protected Booking $booking,
        protected string $manageUrl,
        protected string $reason,
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
        return sprintf(
            'SchedFlow: Your payment receipt was not accepted (%s). Upload again: %s',
            $this->reason,
            $this->manageUrl,
        );
    }
}
