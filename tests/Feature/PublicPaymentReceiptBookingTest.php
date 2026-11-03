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
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function receiptBookingFixture(bool $requiresReceipt = true): array
{
    $host = User::factory()->create();
    $organization = Organization::factory()->create(['timezone' => 'UTC', 'slug' => 'receipt-org-'.uniqid()]);
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
        'slug' => 'paid-slot',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
        'location_type' => LocationType::Link,
        'requires_payment_receipt' => $requiresReceipt,
        'requires_confirmation' => false,
        'payment_instructions' => $requiresReceipt ? 'Pay then upload' : null,
    ]);

    $invitee = User::factory()->create([
        'phone' => '+1555'.random_int(1000000, 9999999),
        'phone_verified_at' => now(),
    ]);

    $start = CarbonImmutable::now('UTC')->next('Tuesday')->setTime(11, 0);

    return compact('host', 'organization', 'schedule', 'eventType', 'invitee', 'start');
}

test('receipt-required book without upload stays pending payment not confirmed', function () {
    [
        'organization' => $organization,
        'eventType' => $eventType,
        'invitee' => $invitee,
        'start' => $start,
    ] = receiptBookingFixture(true);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-receipt@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ]);

    $book->assertCreated()
        ->assertJsonPath('status', BookingStatus::PendingPayment->value)
        ->assertJsonStructure(['actionTokens' => ['manage', 'cancel', 'reschedule'], 'holdExpiresAt']);

    expect(Booking::query()->where('uid', $book->json('uid'))->first()->status)
        ->toBe(BookingStatus::PendingPayment);
});

test('receipt-required happy path uploads then pending confirmation and occupies slot', function () {
    Storage::fake('local');
    [
        'organization' => $organization,
        'eventType' => $eventType,
        'invitee' => $invitee,
        'start' => $start,
    ] = receiptBookingFixture(true);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-upload@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $uid = $book->json('uid');
    $manageToken = $book->json('actionTokens.manage');

    $missing = $this->post(
        "/api/v1/public/bookings/{$uid}/payment-receipt",
        ['token' => $manageToken],
    );
    $missing->assertUnprocessable();

    $file = UploadedFile::fake()->image('paid.jpg')->size(120);
    $upload = $this->post(
        "/api/v1/public/bookings/{$uid}/payment-receipt",
        [
            'token' => $manageToken,
            'file' => $file,
        ],
    );

    $upload->assertCreated()
        ->assertJsonPath('status', BookingStatus::PendingConfirmation->value)
        ->assertJsonPath('paymentReceipt.status', PaymentReceiptStatus::Uploaded->value)
        ->assertJsonPath('holdExpiresAt', null);

    $slots = $this->getJson(
        "/api/v1/public/orgs/{$organization->slug}/event-types/{$eventType->slug}/slots"
        .'?from='.urlencode($start->toIso8601String())
        .'&to='.urlencode($start->addDay()->toIso8601String())
        .'&tz=UTC'
    )->assertOk();

    $starts = collect($slots->json('slots'))->pluck('startAt');
    expect($starts->contains(fn ($value) => CarbonImmutable::parse($value)->equalTo($start)))->toBeFalse();
});

test('payment hold expiry frees slot and marks booking expired', function () {
    Storage::fake('local');
    [
        'organization' => $organization,
        'eventType' => $eventType,
        'invitee' => $invitee,
        'start' => $start,
    ] = receiptBookingFixture(true);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-expire@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])->assertCreated();

    $uid = $book->json('uid');
    $manageToken = $book->json('actionTokens.manage');

    Booking::query()->where('uid', $uid)->update([
        'hold_expires_at' => now()->subMinute(),
    ]);

    $file = UploadedFile::fake()->image('late.jpg')->size(80);
    $this->post(
        "/api/v1/public/bookings/{$uid}/payment-receipt",
        [
            'token' => $manageToken,
            'file' => $file,
        ],
    )->assertUnprocessable();

    expect(Booking::query()->where('uid', $uid)->first()->status)
        ->toBe(BookingStatus::Expired);

    $slots = $this->getJson(
        "/api/v1/public/orgs/{$organization->slug}/event-types/{$eventType->slug}/slots"
        .'?from='.urlencode($start->toIso8601String())
        .'&to='.urlencode($start->addDay()->toIso8601String())
        .'&tz=UTC'
    )->assertOk();

    $starts = collect($slots->json('slots'))->pluck('startAt');
    expect($starts->contains(fn ($value) => CarbonImmutable::parse($value)->equalTo($start)))->toBeTrue();
});

test('non-receipt event types still auto-confirm', function () {
    [
        'organization' => $organization,
        'eventType' => $eventType,
        'invitee' => $invitee,
        'start' => $start,
    ] = receiptBookingFixture(false);

    $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest-free@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ])
        ->assertCreated()
        ->assertJsonPath('status', BookingStatus::Confirmed->value);
});
