<?php

namespace App\Support;

use App\Models\Booking;

class ManageBookingUrl
{
    public static function forBooking(Booking $booking, string $manageToken): string
    {
        $base = rtrim((string) config('services.frontend.url', 'http://localhost:3000'), '/');

        return $base.'/b/'.$booking->uid.'/manage?token='.urlencode($manageToken);
    }
}
