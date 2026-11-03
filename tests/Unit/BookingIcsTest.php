<?php

use App\Support\BookingIcs;
use Carbon\CarbonImmutable;

test('ics uid uses booking uid at schedflow domain', function () {
    expect(BookingIcs::uidForBooking('abc123xyz0'))->toBe('abc123xyz0@schedflow.com');
});

test('ics request includes uid and utc datetimes', function () {
    $start = CarbonImmutable::parse('2030-01-15T15:00:00Z');
    $end = CarbonImmutable::parse('2030-01-15T15:30:00Z');

    $ics = BookingIcs::build(
        bookingUid: 'bookuid01',
        method: 'REQUEST',
        sequence: 0,
        summary: 'Intro Call',
        startAt: $start,
        endAt: $end,
        organizerEmail: 'host@example.com',
        organizerName: 'Host Person',
        attendeeEmail: 'guest@example.com',
        attendeeName: 'Guest',
        location: 'https://meet.example.com/x',
    );

    expect($ics)->toContain('UID:bookuid01@schedflow.com')
        ->and($ics)->toContain('METHOD:REQUEST')
        ->and($ics)->toContain('DTSTART:20300115T150000Z')
        ->and($ics)->toContain('DTEND:20300115T153000Z');
});
