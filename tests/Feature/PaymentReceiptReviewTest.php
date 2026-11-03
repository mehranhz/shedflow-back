<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentReceiptStatus;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JwtTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function reviewFixture(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->create(['timezone' => 'UTC', 'slug' => 'review-org-'.uniqid()]);
    $owner->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Owner->value,
        'status' => MembershipStatus::Active->value,
    ]);

    $member = User::factory()->create();
    $member->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Member->value,
        'status' => MembershipStatus::Active->value,
    ]);
    Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'created_by' => $member->id,
        'timezone' => 'UTC',
        'is_default' => true,
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
        'slug' => 'review-paid',
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

    $start = CarbonImmutable::now('UTC')->next('Wednesday')->setTime(10, 0);
    $ownerToken = 'Bearer '.app(JwtTokenService::class)->signAccessToken(
        $owner,
        $organization->id,
        OrganizationRole::Owner,
    );
    $memberToken = 'Bearer '.app(JwtTokenService::class)->signAccessToken(
        $member,
        $organization->id,
        OrganizationRole::Member,
    );

    return compact(
        'owner',
        'member',
        'organization',
        'eventType',
        'invitee',
        'start',
        'ownerToken',
        'memberToken',
    );
}

function bookAndUploadReceipt(array $fixture): array
{
    Storage::fake('local');
    $book = test()->postJson('/api/v1/public/bookings', [
        'orgSlug' => $fixture['organization']->slug,
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $fixture['start']->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest',
            'email' => 'review-guest-'.uniqid().'@example.com',
            'phone' => $fixture['invitee']->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $uid = $book->json('uid');
    $upload = test()->post(
        "/api/v1/public/bookings/{$uid}/payment-receipt",
        [
            'token' => $book->json('actionTokens.manage'),
            'file' => UploadedFile::fake()->image('proof.jpg')->size(90),
        ],
    )->assertCreated();

    $bookingId = Booking::query()->where('uid', $uid)->value('id');

    return [
        'uid' => $uid,
        'bookingId' => $bookingId,
        'manageToken' => $book->json('actionTokens.manage'),
        'upload' => $upload,
    ];
}

test('staff approve confirms booking and public manage shows confirmed', function () {
    $fixture = reviewFixture();
    $booked = bookAndUploadReceipt($fixture);

    $approve = $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt/approve");

    $approve->assertOk()
        ->assertJsonPath('status', BookingStatus::Confirmed->value)
        ->assertJsonPath('paymentReceipt.status', PaymentReceiptStatus::Approved->value);

    $this->getJson("/api/v1/public/bookings/{$booked['uid']}")
        ->assertOk()
        ->assertJsonPath('status', BookingStatus::Confirmed->value)
        ->assertJsonPath('paymentReceipt.status', PaymentReceiptStatus::Approved->value);

    // Idempotent second approve
    $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt/approve")
        ->assertOk()
        ->assertJsonPath('status', BookingStatus::Confirmed->value);
});

test('staff reject requires reason and allows re-upload', function () {
    $fixture = reviewFixture();
    $booked = bookAndUploadReceipt($fixture);

    $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt/reject", [])
        ->assertUnprocessable();

    $reject = $this->withHeader('Authorization', $fixture['ownerToken'])
        ->postJson(
            "/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt/reject",
            ['reason' => 'Amount does not match invoice'],
        );

    $reject->assertOk()
        ->assertJsonPath('status', BookingStatus::PendingPayment->value)
        ->assertJsonPath('paymentReceipt.status', PaymentReceiptStatus::Rejected->value)
        ->assertJsonPath('paymentReceipt.rejectionReason', 'Amount does not match invoice');

    $this->getJson("/api/v1/public/bookings/{$booked['uid']}")
        ->assertOk()
        ->assertJsonPath('paymentReceipt.rejectionReason', 'Amount does not match invoice');

    $reupload = $this->post(
        "/api/v1/public/bookings/{$booked['uid']}/payment-receipt",
        [
            'token' => $booked['manageToken'],
            'file' => UploadedFile::fake()->image('fixed.jpg')->size(100),
        ],
    );

    $reupload->assertCreated()
        ->assertJsonPath('status', BookingStatus::PendingConfirmation->value)
        ->assertJsonPath('paymentReceipt.status', PaymentReceiptStatus::Uploaded->value);
});

test('member cannot review another hosts booking receipt', function () {
    $fixture = reviewFixture();
    $booked = bookAndUploadReceipt($fixture);

    $this->withHeader('Authorization', $fixture['memberToken'])
        ->getJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt")
        ->assertNotFound();

    $this->withHeader('Authorization', $fixture['memberToken'])
        ->postJson("/api/v1/organizations/{$fixture['organization']->id}/bookings/{$booked['bookingId']}/payment-receipt/approve")
        ->assertNotFound();
});
