<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JwtTokenService;
use App\Support\Jwt;

test('signed-in client lists bookings whose invitee phone matches theirs', function () {
    $host = User::factory()->create();
    $organization = Organization::factory()->create();
    $schedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $host->id,
    ]);
    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $host->id,
        'schedule_id' => $schedule->id,
    ]);

    $owner = User::factory()->create([
        'phone' => '+15550001111',
    ]);
    expect($owner->organizations)->toBeEmpty();

    $other = User::factory()->create([
        'phone' => '+15550002222',
    ]);

    $byPhone = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => $owner->phone,
        'user_id' => null,
        'email' => 'owner-phone@example.com',
    ]);
    $byUserOnly = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => '+15550009999',
        'user_id' => $owner->id,
        'email' => 'owner-id@example.com',
    ]);
    $strangerCustomer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => $other->phone,
        'user_id' => $other->id,
        'email' => 'other@example.com',
    ]);

    $phoneBooking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $byPhone->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $idOnlyBooking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $byUserOnly->id,
        'status' => BookingStatus::Confirmed,
    ]);
    $strangerBooking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $strangerCustomer->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $ownerToken = app(JwtTokenService::class)->signAccessToken($owner);
    $claims = Jwt::decode($ownerToken);
    expect($claims['phone'])->toBe($owner->phone)
        ->and($claims['actorType'])->toBe('customer')
        ->and($claims)->not->toHaveKey('orgId')
        ->and($claims)->not->toHaveKey('role');

    $listed = $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken])
        ->getJson('/api/v1/me/bookings');

    $listed->assertOk();
    expect($listed->json())->toBeArray()
        ->and($listed->json())->toHaveCount(2)
        ->and(array_is_list($listed->json()))->toBeTrue()
        ->and($listed->json())->not->toHaveKey('success')
        ->and($listed->json())->not->toHaveKey('data');

    expect(collect($listed->json())->pluck('id'))
        ->toContain($phoneBooking->id, $idOnlyBooking->id)
        ->not->toContain($strangerBooking->id);

    $otherToken = app(JwtTokenService::class)->signAccessToken($other);
    $otherList = $this->withHeaders(['Authorization' => 'Bearer '.$otherToken])
        ->getJson('/api/v1/me/bookings');
    $otherList->assertOk();
    expect(collect($otherList->json())->pluck('id'))
        ->toContain($strangerBooking->id)
        ->not->toContain($phoneBooking->id, $idOnlyBooking->id);

    $empty = User::factory()->create([
        'phone' => '+15550003333',
    ]);
    $emptyToken = app(JwtTokenService::class)->signAccessToken($empty);
    $this->withHeaders(['Authorization' => 'Bearer '.$emptyToken])
        ->getJson('/api/v1/me/bookings')
        ->assertOk()
        ->assertExactJson([]);
});

test('signed-in client cancels only a booking whose invitee phone matches theirs', function () {
    $host = User::factory()->create();
    $organization = Organization::factory()->create();
    $schedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $host->id,
    ]);
    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $host->id,
        'schedule_id' => $schedule->id,
    ]);

    $owner = User::factory()->create([
        'phone' => '+15550001111',
    ]);
    expect($owner->organizations)->toBeEmpty();

    $other = User::factory()->create([
        'phone' => '+15550002222',
    ]);

    $ownedCustomer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => $owner->phone,
        'user_id' => null,
        'email' => 'owner-phone@example.com',
    ]);
    $strangerCustomer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => $other->phone,
        'user_id' => $other->id,
        'email' => 'other@example.com',
    ]);

    $start = now()->utc()->addDays(3)->startOfHour();
    $ownedBooking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $ownedCustomer->id,
        'start_at' => $start,
        'end_at' => $start->copy()->addMinutes(30),
        'status' => BookingStatus::Confirmed,
    ]);
    $strangerBooking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $strangerCustomer->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $ownerToken = app(JwtTokenService::class)->signAccessToken($owner);
    $claims = Jwt::decode($ownerToken);
    expect($claims['phone'])->toBe($owner->phone)
        ->and($claims)->not->toHaveKey('orgId');

    $this->postJson("/api/v1/me/bookings/{$ownedBooking->id}/cancel")
        ->assertUnauthorized();

    $denied = $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken])
        ->postJson("/api/v1/me/bookings/{$strangerBooking->id}/cancel", [
            'reason' => 'not mine',
        ]);

    $denied->assertNotFound();
    expect($strangerBooking->refresh()->status)->toBe(BookingStatus::Confirmed);

    $cancelled = $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken])
        ->postJson("/api/v1/me/bookings/{$ownedBooking->id}/cancel", [
            'reason' => 'changed plans',
        ]);

    $cancelled->assertOk();
    expect($cancelled->json())->not->toHaveKey('success')
        ->and($cancelled->json())->not->toHaveKey('data')
        ->and($cancelled->json('id'))->toBe($ownedBooking->id)
        ->and($cancelled->json('status'))->toBe(BookingStatus::Cancelled->value)
        ->and($cancelled->json('cancellationReason'))->toBe('changed plans');

    expect($ownedBooking->refresh()->status)->toBe(BookingStatus::Cancelled);

    $again = $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken])
        ->postJson("/api/v1/me/bookings/{$ownedBooking->id}/cancel");

    $again->assertStatus(409);
});
