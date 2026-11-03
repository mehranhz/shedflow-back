<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function customerBookingFixture(int $cancellationNoticeHours = 0, int $rescheduleNoticeHours = 0): array
{
    ['user' => $host, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $host->id,
        'schedule_id' => $schedule->id,
        'slug' => 'portal-call',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => $cancellationNoticeHours,
        'reschedule_notice_hours' => $rescheduleNoticeHours,
        'is_active' => true,
        'is_hidden' => false,
    ]);

    $email = fake()->unique()->safeEmail();

    $customerUser = User::factory()->create([
        'email' => $email,
        'phone' => '+1555'.fake()->unique()->numerify('#######'),
        'phone_verified_at' => now(),
    ]);

    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $customerUser->id,
        'email' => $email,
    ]);

    $start = CarbonImmutable::now('UTC')->next('Wednesday')->setTime(14, 0);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $customer->id,
        'start_at' => $start,
        'end_at' => $start->addMinutes(30),
        'status' => BookingStatus::Confirmed,
    ]);

    return compact('host', 'organization', 'schedule', 'eventType', 'customerUser', 'customer', 'booking', 'start');
}

test('customer jwt can cancel inside policy and rejects outside policy window', function () {
    $fixture = customerBookingFixture(cancellationNoticeHours: 24);
    $token = customerAccessToken($fixture['customerUser'], $fixture['organization']->id);

    $ok = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(
            "/api/v1/customer/bookings/{$fixture['booking']->uid}/cancel",
            ['reason' => 'plans changed'],
            ['Idempotency-Key' => (string) Str::uuid()],
        );

    $ok->assertOk()
        ->assertJsonPath('status', BookingStatus::Cancelled->value)
        ->assertJsonPath('cancellationReason', 'plans changed');

    $tooSoon = customerBookingFixture(cancellationNoticeHours: 72);
    $soonStart = CarbonImmutable::now('UTC')->addHours(2);
    $tooSoon['booking']->update(['start_at' => $soonStart, 'end_at' => $soonStart->addMinutes(30)]);

    $tokenSoon = customerAccessToken($tooSoon['customerUser'], $tooSoon['organization']->id);

    $denied = $this->withHeader('Authorization', 'Bearer '.$tokenSoon)
        ->postJson(
            "/api/v1/customer/bookings/{$tooSoon['booking']->uid}/cancel",
            [],
            ['Idempotency-Key' => (string) Str::uuid()],
        );

    $denied->assertStatus(422)
        ->assertJsonPath('error.code', 'OUTSIDE_POLICY');
});

test('customer cancel idor returns not found for another customers booking uid', function () {
    $owned = customerBookingFixture();
    $stranger = customerBookingFixture();

    $token = customerAccessToken($owned['customerUser'], $owned['organization']->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(
            "/api/v1/customer/bookings/{$stranger['booking']->uid}/cancel",
            [],
            ['Idempotency-Key' => (string) Str::uuid()],
        )
        ->assertNotFound();
});

test('public signed cancel still works without customer login', function () {
    $fixture = customerBookingFixture(cancellationNoticeHours: 0);

    $invitee = User::factory()->create([
        'phone' => '+15553334444',
        'phone_verified_at' => now(),
    ]);

    $start = CarbonImmutable::now('UTC')->next('Thursday')->setTime(10, 0);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $fixture['organization']->slug,
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest',
            'email' => 'guest-token@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ]);

    $book->assertCreated();
    $uid = $book->json('uid');
    $cancelToken = $book->json('actionTokens.cancel');

    $this->postJson("/api/v1/public/bookings/{$uid}/cancel", [
        'token' => $cancelToken,
    ])->assertOk()
        ->assertJsonPath('status', BookingStatus::Cancelled->value);
});

test('concurrent customer reschedule yields one success and one slot unavailable', function () {
    $fixture = customerBookingFixture(rescheduleNoticeHours: 0);
    $token = customerAccessToken($fixture['customerUser'], $fixture['organization']->id);

    $target = CarbonImmutable::now('UTC')->next('Friday')->setTime(15, 0);
    $payload = [
        'startAt' => $target->toIso8601String(),
        'timezone' => 'UTC',
    ];
    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    $first = $this->postJson(
        "/api/v1/customer/bookings/{$fixture['booking']->uid}/reschedule",
        $payload,
        $headers,
    );

    $secondBooking = Booking::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'event_type_id' => $fixture['eventType']->id,
        'host_user_id' => $fixture['host']->id,
        'customer_id' => $fixture['customer']->id,
        'start_at' => $fixture['start']->addDay(),
        'end_at' => $fixture['start']->addDay()->addMinutes(30),
        'status' => BookingStatus::Confirmed,
    ]);

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson(
        "/api/v1/customer/bookings/{$secondBooking->uid}/reschedule",
        $payload,
    );

    $statuses = collect([$first->status(), $second->status()])->sort()->values()->all();
    expect($statuses)->toBe([200, 409]);

    $conflict = $first->status() === 409 ? $first : $second;
    $conflict->assertJsonPath('error.code', 'SLOT_UNAVAILABLE');
});

test('customer can patch profile with if-match and rejects stale version', function () {
    $fixture = customerBookingFixture();
    $token = customerAccessToken($fixture['customerUser'], $fixture['organization']->id);

    $show = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/customer/profile');

    $show->assertOk()
        ->assertJsonPath('data.name', $fixture['customer']->name);

    $version = $show->json('data.updatedAt');
    $this->travel(2)->seconds();

    $patch = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'If-Match' => $version,
    ])->patchJson('/api/v1/customer/profile', [
        'name' => 'Updated Name',
        'locale' => 'fr',
    ]);

    $patch->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.locale', 'fr');

    $stale = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'If-Match' => $version,
    ])->patchJson('/api/v1/customer/profile', [
        'name' => 'Should Fail',
    ]);

    $stale->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('authenticated customer can book for active org', function () {
    $fixture = customerBookingFixture();
    $token = customerAccessToken($fixture['customerUser'], $fixture['organization']->id);

    $start = CarbonImmutable::now('UTC')->next('Monday')->setTime(16, 0);

    $created = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Idempotency-Key' => (string) Str::uuid(),
    ])->postJson('/api/v1/customer/bookings', [
        'eventTypeSlug' => $fixture['eventType']->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
    ]);

    $created->assertCreated()
        ->assertJsonPath('status', BookingStatus::Confirmed->value)
        ->assertJsonPath('customerId', $fixture['customer']->id);
});
