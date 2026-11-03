<?php

use App\Enums\ActorType;
use App\Enums\BookingStatus;
use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerUserLinkEvent;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\CustomerLinkService;
use App\Services\JwtTokenService;
use App\Support\Jwt;

test('customer-only jwt cannot list organization bookings', function () {
    ['user' => $host, 'organization' => $organization] = seedOrgWithDefaultSchedule();

    $customerUser = User::factory()->create();
    Customer::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $customerUser->id,
    ]);

    $token = app(JwtTokenService::class)->signAccessToken($customerUser);
    $claims = Jwt::decode($token);
    expect($claims['actorType'])->toBe(ActorType::Customer->value)
        ->and($claims)->not->toHaveKey('orgId')
        ->and($claims)->not->toHaveKey('role');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/organizations/{$organization->id}/bookings")
        ->assertForbidden();
});

test('staff user with customer link receives staff actor and org rbac', function () {
    ['user' => $owner, 'organization' => $organization] = seedOrgWithDefaultSchedule();

    Customer::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);

    $token = app(JwtTokenService::class)->signAccessToken(
        $owner,
        $organization->id,
        OrganizationRole::Owner,
    );
    $claims = Jwt::decode($token);
    expect($claims['actorType'])->toBe(ActorType::Staff->value)
        ->and($claims['orgId'])->toBe($organization->id)
        ->and($claims['role'])->toBe(OrganizationRole::Owner->value);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/organizations/{$organization->id}/bookings")
        ->assertOk();
});

test('link and unlink customer user writes audit events', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => null,
    ]);

    $service = app(CustomerLinkService::class);
    $service->linkCustomerToUser($customer, $user);

    expect($customer->fresh()->user_id)->toBe($user->id);
    expect(CustomerUserLinkEvent::query()->where('action', 'linked')->count())->toBe(1);

    $service->unlinkCustomerFromUser($customer->fresh(), $user);
    expect($customer->fresh()->user_id)->toBeNull();
    expect(CustomerUserLinkEvent::query()->where('action', 'unlinked')->count())->toBe(1);
});

test('me bookings includes rows linked by customer user id', function () {
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

    $customerUser = User::factory()->create(['phone' => '+15550004444']);
    $linkedCustomer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'phone' => '+15559998888',
        'user_id' => $customerUser->id,
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $host->id,
        'customer_id' => $linkedCustomer->id,
        'status' => BookingStatus::Confirmed,
    ]);

    $token = app(JwtTokenService::class)->signAccessToken($customerUser);
    $listed = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/me/bookings');

    $listed->assertOk();
    expect(collect($listed->json())->pluck('id'))->toContain($booking->id);
});

test('login session for customer-only user includes actorType customer', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create([
        'phone' => '+15550005555',
        'password' => 'password',
    ]);
    Customer::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => $user->phone,
        'password' => 'password',
    ]);

    $response->assertOk();
    expect($response->json('data.actorType'))->toBe(ActorType::Customer->value);

    $claims = Jwt::decode((string) $response->json('data.accessToken'));
    expect($claims['actorType'])->toBe(ActorType::Customer->value);
});
