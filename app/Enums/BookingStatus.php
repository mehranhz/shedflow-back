<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case PendingConfirmation = 'PENDING_CONFIRMATION';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Rescheduled = 'RESCHEDULED';
    case Expired = 'EXPIRED';
    case NoShow = 'NO_SHOW';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::PendingPayment,
            self::PendingConfirmation,
            self::Confirmed,
        ];
    }
}
