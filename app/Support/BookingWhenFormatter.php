<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class BookingWhenFormatter
{
    public static function label(
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        string $timezone,
    ): string {
        try {
            $start = $startAt->timezone($timezone);
            $end = $endAt->timezone($timezone);

            return $start->isoFormat('dddd, MMMM D, YYYY').' · '
                .$start->format('g:i A').' – '.$end->format('g:i A').' ('.$timezone.')';
        } catch (\Throwable) {
            return $startAt->toIso8601String().' – '.$endAt->toIso8601String();
        }
    }
}
