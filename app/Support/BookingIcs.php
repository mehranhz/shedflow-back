<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class BookingIcs
{
    /** UID format required by design: `{booking.uid}@schedflow.com`. */
    public static function uidForBooking(string $bookingUid): string
    {
        return $bookingUid.'@schedflow.com';
    }

    /**
     * @param  'REQUEST'|'CANCEL'  $method
     */
    public static function build(
        string $bookingUid,
        string $method,
        int $sequence,
        string $summary,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        string $organizerEmail,
        ?string $organizerName,
        string $attendeeEmail,
        ?string $attendeeName,
        ?string $location = null,
        ?string $description = null,
    ): string {
        $uid = self::uidForBooking($bookingUid);
        $stamp = self::formatUtc(now());
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SchedFlow//EN',
            'METHOD:'.$method,
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.self::formatUtc($startAt),
            'DTEND:'.self::formatUtc($endAt),
            'SUMMARY:'.self::escapeText($summary),
            'SEQUENCE:'.$sequence,
            'STATUS:'.($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'),
            'ORGANIZER;CN='.self::escapeText(trim($organizerName ?? '') !== '' ? $organizerName : $organizerEmail)
                .':mailto:'.$organizerEmail,
            'ATTENDEE;CN='.self::escapeText(trim($attendeeName ?? '') !== '' ? $attendeeName : $attendeeEmail)
                .';RSVP=TRUE:mailto:'.$attendeeEmail,
        ];

        if ($description !== null && $description !== '') {
            $lines[] = 'DESCRIPTION:'.self::escapeText($description);
        }
        if ($location !== null && $location !== '') {
            $lines[] = 'LOCATION:'.self::escapeText($location);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private static function formatUtc(CarbonInterface $date): string
    {
        return strtoupper($date->utc()->format('Ymd\THis\Z'));
    }

    private static function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $value,
        );
    }
}
