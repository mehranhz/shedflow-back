<?php

namespace App\Services;

use App\Exceptions\ApiDomainException;
use App\Models\Booking;
use Carbon\CarbonImmutable;

class BookingInviteePolicy
{
    public function assertCanCancel(Booking $booking, bool $hostOverride = false): void
    {
        if ($hostOverride) {
            return;
        }

        $booking->loadMissing('eventType');
        $noticeHours = (int) ($booking->eventType?->cancellation_notice_hours ?? 24);
        $earliest = CarbonImmutable::now()->addHours($noticeHours);

        if ($booking->start_at && $booking->start_at->lt($earliest)) {
            throw new ApiDomainException(
                'OUTSIDE_POLICY',
                'Cancellation is outside the allowed notice window',
                422,
            );
        }
    }

    public function assertCanReschedule(Booking $booking, bool $hostOverride = false): void
    {
        if ($hostOverride) {
            return;
        }

        $booking->loadMissing('eventType');
        $noticeHours = (int) ($booking->eventType?->reschedule_notice_hours ?? 24);
        $earliest = CarbonImmutable::now()->addHours($noticeHours);

        if ($booking->start_at && $booking->start_at->lt($earliest)) {
            throw new ApiDomainException(
                'OUTSIDE_POLICY',
                'Reschedule is outside the allowed notice window',
                422,
            );
        }
    }
}
