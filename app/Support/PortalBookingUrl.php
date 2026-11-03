<?php

namespace App\Support;

use App\Models\Booking;

class PortalBookingUrl
{
    public static function forBooking(Booking $booking): string
    {
        $base = rtrim((string) config('services.frontend.url', 'http://localhost:3000'), '/');

        return $base.'/portal/appointments/'.$booking->uid;
    }
}
