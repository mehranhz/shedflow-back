<?php

use App\Enums\BookingStatus;
use App\Enums\EmailTemplate;
use App\Enums\LocationType;
use App\Enums\MembershipStatus;
use App\Enums\NotificationStatus;
use App\Enums\OrganizationRole;
use App\Mail\BookingTemplateMail;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\NotificationLog;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\BookingNotificationService;
use App\Support\BookingIcs;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function bookingEmailFixture(): array
{
    $host = User::factory()->create();
    $organization = Organization::factory()->create([
        'timezone' => 'UTC',
        'slug' => 'email-org-'.uniqid(),
        'settings' => [
            'notifications' => [
                'hostEmail' => 'host-notify@example.com',
            ],
        ],
    ]);
    $host->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Owner->value,
        'status' => MembershipStatus::Active->value,
    ]);

    $schedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $host->id,
        'created_by' => $host->id,
        'timezone' => 'UTC',
        'is_default' => true,
    ]);

    foreach ([1, 2, 3, 4, 5] as $day) {
        AvailabilityRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $day,
            'start_minute' => 540,
            'end_minute' => 1020,
        ]);
    }

    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $host->id,
        'schedule_id' => $schedule->id,
        'slug' => 'email-demo',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
        'location_type' => LocationType::Link,
        'requires_payment_receipt' => false,
        'requires_confirmation' => false,
    ]);

    $invitee = User::factory()->create([
        'phone' => '+1555'.random_int(1000000, 9999999),
        'phone_verified_at' => now(),
    ]);

    $start = CarbonImmutable::now('UTC')->next('Thursday')->setTime(10, 0);

    return compact('host', 'organization', 'eventType', 'invitee', 'start');
}

test('auto confirmed public booking creates host and invitee notification logs', function () {
    Mail::fake();
    Queue::fake();
    ['organization' => $organization, 'eventType' => $eventType, 'invitee' => $invitee, 'start' => $start] = bookingEmailFixture();

    $response = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-confirmed@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ]);

    $response->assertCreated()->assertJsonPath('status', BookingStatus::Confirmed->value);

    $booking = Booking::query()->where('uid', $response->json('uid'))->first();
    expect($booking)->not->toBeNull();

    $logs = NotificationLog::query()->where('booking_id', $booking->id)->orderBy('template')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('template')->sort()->values()->all())->toEqual([
            EmailTemplate::BookingConfirmedHost->value,
            EmailTemplate::BookingConfirmedInvitee->value,
        ])
        ->and($logs->every(fn ($log) => $log->status === NotificationStatus::Sent))->toBeTrue();

    Mail::assertSent(BookingTemplateMail::class, 2);
});

test('duplicate confirmed delivery does not insert a second sent row', function () {
    Mail::fake();
    Queue::fake();
    ['organization' => $organization, 'eventType' => $eventType, 'invitee' => $invitee, 'start' => $start] = bookingEmailFixture();

    $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-dup@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $booking = Booking::query()->whereHas('customer', fn ($q) => $q->where('email', 'guest-dup@example.com'))->first();
    $service = app(BookingNotificationService::class);

    $service->sendConfirmed($booking, ['manage' => 'token-manage']);
    $service->sendConfirmed($booking, ['manage' => 'token-manage']);

    expect(NotificationLog::query()->where('booking_id', $booking->id)->count())->toBe(2);
    Mail::assertSent(BookingTemplateMail::class, 2);
});

test('confirmed invitee email includes portal deep link when customer_portal enabled', function () {
    Mail::fake();
    Queue::fake();
    config(['features.customer_portal' => true, 'services.frontend.url' => 'https://app.example.test']);

    ['organization' => $organization, 'eventType' => $eventType, 'invitee' => $invitee, 'start' => $start] = bookingEmailFixture();

    $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-portal@link.example',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $booking = Booking::query()->whereHas('customer', fn ($q) => $q->where('email', 'guest-portal@link.example'))->first();
    expect($booking)->not->toBeNull();

    Mail::assertSent(BookingTemplateMail::class, function (BookingTemplateMail $mail) use ($booking) {
        if (! str_contains($mail->subjectLine, "You're booked")) {
            return false;
        }

        return str_contains($mail->htmlBody, '/portal/appointments/'.$booking->uid)
            && str_contains($mail->textBody, 'Manage in SchedFlow')
            && str_contains($mail->htmlBody, 'Manage without signing in');
    });
});

test('confirmed invitee email attaches ics with schedflow uid', function () {
    Mail::fake();
    Queue::fake();
    ['organization' => $organization, 'eventType' => $eventType, 'invitee' => $invitee, 'start' => $start] = bookingEmailFixture();

    $response = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-ics@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $uid = $response->json('uid');
    expect(BookingIcs::uidForBooking($uid))->toBe("{$uid}@schedflow.com");

    Mail::assertSent(BookingTemplateMail::class, function (BookingTemplateMail $mail) use ($uid) {
        if ($mail->icsAttachment === null) {
            return false;
        }

        return str_contains($mail->icsAttachment['content'], 'UID:'.$uid.'@schedflow.com');
    });
});
