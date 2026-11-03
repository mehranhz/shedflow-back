<?php

use App\Enums\BookingStatus;
use App\Enums\EmailTemplate;
use App\Enums\NotificationStatus;
use App\Jobs\SendBookingReminderJob;
use App\Mail\BookingTemplateMail;
use App\Models\Booking;
use App\Models\NotificationLog;
use App\Services\BookingNotificationService;
use App\Services\BookingReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('confirmed booking schedules only the 1h reminder when start is in two hours', function () {
    Queue::fake();
    ['organization' => $organization, 'eventType' => $eventType] = bookingEmailFixture();

    $start = CarbonImmutable::now('UTC')->addHours(2)->startOfMinute();
    $customer = \App\Models\Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'guest-reminder@example.com',
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $eventType->host_user_id,
        'customer_id' => $customer->id,
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
        'timezone' => 'UTC',
        'status' => BookingStatus::Confirmed,
    ]);

    $now = CarbonImmutable::now('UTC');
    app(BookingReminderService::class)->scheduleForBooking($booking, $now);

    Queue::assertPushed(SendBookingReminderJob::class, 1);
    Queue::assertPushed(SendBookingReminderJob::class, function (SendBookingReminderJob $job) use ($booking, $start) {
        $expectedRunAt = $start->subSeconds(3600);

        return $job->bookingId === $booking->id
            && $job->template === EmailTemplate::Reminder1h
            && $job->startAtIso === $start->toIso8601String()
            && $job->delay instanceof \DateTimeInterface
            && CarbonImmutable::instance($job->delay)->equalTo($expectedRunAt);
    });
});

test('send confirmed dispatches reminder jobs for a far future booking', function () {
    Queue::fake();
    Mail::fake();
    ['organization' => $organization, 'eventType' => $eventType] = bookingEmailFixture();

    $start = CarbonImmutable::now('UTC')->addDays(5)->setTime(14, 0);
    $customer = \App\Models\Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'far-future@example.com',
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $eventType->host_user_id,
        'customer_id' => $customer->id,
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
        'timezone' => 'UTC',
        'status' => BookingStatus::Confirmed,
    ])->load(['customer', 'eventType', 'host', 'organization']);

    app(BookingNotificationService::class)->sendConfirmed($booking);

    Queue::assertPushed(SendBookingReminderJob::class, 2);
});

test('reminder job no-ops for cancelled bookings', function () {
    Mail::fake();
    ['organization' => $organization, 'eventType' => $eventType] = bookingEmailFixture();

    $start = CarbonImmutable::now('UTC')->addDay();
    $customer = \App\Models\Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'cancelled-guest@example.com',
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $eventType->host_user_id,
        'customer_id' => $customer->id,
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
        'timezone' => 'UTC',
        'status' => BookingStatus::Cancelled,
    ]);

    app(BookingReminderService::class)->sendIfDue(
        $booking->id,
        EmailTemplate::Reminder1h,
        $start->toIso8601String(),
    );

    expect(NotificationLog::query()->where('booking_id', $booking->id)->count())->toBe(0);
    Mail::assertNothingSent();
});

test('reminder job no-ops when start time changed since scheduling', function () {
    Mail::fake();
    ['organization' => $organization, 'eventType' => $eventType] = bookingEmailFixture();

    $start = CarbonImmutable::now('UTC')->addDay();
    $customer = \App\Models\Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'rescheduled-guest@example.com',
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $eventType->host_user_id,
        'customer_id' => $customer->id,
        'start_at' => $start->addHour(),
        'end_at' => $start->addHours(1)->addMinutes(30),
        'timezone' => 'UTC',
        'status' => BookingStatus::Confirmed,
    ]);

    app(BookingReminderService::class)->sendIfDue(
        $booking->id,
        EmailTemplate::Reminder24h,
        $start->toIso8601String(),
    );

    expect(NotificationLog::query()->where('booking_id', $booking->id)->count())->toBe(0);
    Mail::assertNothingSent();
});

test('due reminder delivers host and invitee notification logs', function () {
    Mail::fake();
    ['organization' => $organization, 'eventType' => $eventType] = bookingEmailFixture();

    $start = CarbonImmutable::now('UTC')->addDay();
    $customer = \App\Models\Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'reminder-due@example.com',
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $eventType->host_user_id,
        'customer_id' => $customer->id,
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
        'timezone' => 'UTC',
        'status' => BookingStatus::Confirmed,
    ]);

    app(BookingReminderService::class)->sendIfDue(
        $booking->id,
        EmailTemplate::Reminder1h,
        $start->toIso8601String(),
    );

    $templates = NotificationLog::query()
        ->where('booking_id', $booking->id)
        ->orderBy('template')
        ->pluck('template')
        ->all();

    expect($templates)->toEqual([
        EmailTemplate::Reminder1hHost->value,
        EmailTemplate::Reminder1hInvitee->value,
    ])
        ->and(NotificationLog::query()->where('booking_id', $booking->id)->get()
            ->every(fn ($log) => $log->status === NotificationStatus::Sent))->toBeTrue();

    Mail::assertSent(BookingTemplateMail::class, 2);
});

test('expire payment holds command marks stale pending payment bookings expired', function () {
    ['organization' => $organization, 'eventType' => $eventType, 'invitee' => $invitee, 'start' => $start] = bookingEmailFixture();

    $eventType->update(['requires_payment_receipt' => true, 'requires_confirmation' => true]);
    $eventType->refresh();

    $response = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Hold Guest',
            'email' => 'hold-expire-cmd@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $booking = Booking::query()->where('uid', $response->json('uid'))->first();
    expect($booking->status)->toBe(BookingStatus::PendingPayment);

    $booking->update(['hold_expires_at' => now()->subMinute()]);

    Artisan::call('bookings:expire-payment-holds');

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired);
});
