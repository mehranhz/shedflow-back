<?php

use App\DTOs\ContactDTO;
use App\Enums\LocationType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\AvailabilityRule;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\PaymentReceiptApproved;
use App\Notifications\PaymentReceiptRejected;
use App\Notifications\PaymentReceiptUploaded;
use App\Services\JwtTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function notificationFixture(): array
{
    $owner = User::factory()->create(['phone' => '+15550001111']);
    $organization = Organization::factory()->create(['timezone' => 'UTC', 'slug' => 'notify-org-'.uniqid()]);
    $owner->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Owner->value,
        'status' => MembershipStatus::Active->value,
    ]);

    $schedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'created_by' => $owner->id,
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
        'host_user_id' => $owner->id,
        'schedule_id' => $schedule->id,
        'slug' => 'notify-paid',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
        'location_type' => LocationType::Link,
        'requires_payment_receipt' => true,
    ]);

    $invitee = User::factory()->create([
        'phone' => '+1555'.random_int(1000000, 9999999),
        'phone_verified_at' => now(),
    ]);
    $start = CarbonImmutable::now('UTC')->next('Wednesday')->setTime(11, 0);
    $ownerToken = 'Bearer '.app(JwtTokenService::class)->signAccessToken(
        $owner,
        $organization->id,
        OrganizationRole::Owner,
    );

    return compact('owner', 'organization', 'eventType', 'invitee', 'start', 'ownerToken');
}

test('upload notifies staff via sms channel', function () {
    Notification::fake();
    Storage::fake('local');
    $fixture = notificationFixture();

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $fixture['organization']->slug,
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $fixture['start']->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest',
            'email' => 'notify-guest-'.uniqid().'@example.com',
            'phone' => $fixture['invitee']->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $this->post(
        '/api/v1/public/bookings/'.$book->json('uid').'/payment-receipt',
        [
            'token' => $book->json('actionTokens.manage'),
            'file' => UploadedFile::fake()->image('proof.jpg')->size(90),
        ],
    )->assertCreated();

    Notification::assertSentTo($fixture['owner'], PaymentReceiptUploaded::class);
});

test('approve notifies invitee and idempotent approve does not resend', function () {
    Notification::fake();
    Storage::fake('local');
    $fixture = notificationFixture();

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $fixture['organization']->slug,
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $fixture['start']->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest',
            'email' => 'notify-guest-'.uniqid().'@example.com',
            'phone' => $fixture['invitee']->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $bookingId = $book->json('id');
    $this->post(
        '/api/v1/public/bookings/'.$book->json('uid').'/payment-receipt',
        [
            'token' => $book->json('actionTokens.manage'),
            'file' => UploadedFile::fake()->image('proof.jpg')->size(90),
        ],
    )->assertCreated();

    Notification::fake();

    $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$bookingId}/payment-receipt/approve")
        ->assertOk();

    Notification::assertSentTo(
        new ContactDTO($fixture['invitee']->phone),
        PaymentReceiptApproved::class,
    );

    $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$bookingId}/payment-receipt/approve")
        ->assertOk();

    Notification::assertSentTimes(PaymentReceiptApproved::class, 1);
});

test('reject notifies invitee with reason', function () {
    Notification::fake();
    Storage::fake('local');
    $fixture = notificationFixture();

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $fixture['organization']->slug,
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $fixture['start']->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest',
            'email' => 'notify-guest-'.uniqid().'@example.com',
            'phone' => $fixture['invitee']->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $bookingId = $book->json('id');
    $this->post(
        '/api/v1/public/bookings/'.$book->json('uid').'/payment-receipt',
        [
            'token' => $book->json('actionTokens.manage'),
            'file' => UploadedFile::fake()->image('proof.jpg')->size(90),
        ],
    )->assertCreated();

    Notification::fake();

    $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson(
            "/api/v1/organizations/{$fixture['organization']->id}/bookings/{$bookingId}/payment-receipt/reject",
            ['reason' => 'Amount mismatch'],
        )
        ->assertOk();

    Notification::assertSentTo(
        new ContactDTO($fixture['invitee']->phone),
        PaymentReceiptRejected::class,
        function (PaymentReceiptRejected $notification) {
            return str_contains($notification->toSms(new ContactDTO('+1')), 'Amount mismatch');
        },
    );
});
