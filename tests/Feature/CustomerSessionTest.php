<?php

use App\Enums\ActorType;
use App\Enums\BookingStatus;
use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JwtTokenService;
use App\Support\Jwt;

test('customer me lists linked businesses and rejects staff tokens', function () {
    ['user' => $owner, 'organization' => $orgA] = seedOrgWithDefaultSchedule();
    $orgB = Organization::factory()->create(['name' => 'Beta Co']);

    $customerUser = User::factory()->create(['email' => 'multi@example.com']);
    $customerA = Customer::factory()->create([
        'organization_id' => $orgA->id,
        'user_id' => $customerUser->id,
    ]);
    $customerB = Customer::factory()->create([
        'organization_id' => $orgB->id,
        'user_id' => $customerUser->id,
    ]);

    $staffToken = app(JwtTokenService::class)->signAccessToken(
        $owner,
        $orgA->id,
        OrganizationRole::Owner,
    );

    $this->withHeader('Authorization', 'Bearer '.$staffToken)
        ->getJson('/api/v1/customer/me')
        ->assertForbidden();

    $response = $this->withHeader('Authorization', 'Bearer '.customerAccessToken($customerUser))
        ->getJson('/api/v1/customer/me');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.businesses');

    $orgIds = collect($response->json('data.businesses'))->pluck('organizationId');
    expect($orgIds)->toContain($orgA->id, $orgB->id);

    $byOrg = collect($response->json('data.businesses'))->keyBy('organizationId');
    expect($byOrg[$orgA->id]['customerId'])->toBe($customerA->id)
        ->and($byOrg[$orgB->id]['customerId'])->toBe($customerB->id);
});

test('switching customer org scopes booking lists and blocks foreign orgs', function () {
    $host = User::factory()->create();
    $orgA = Organization::factory()->create(['slug' => 'org-a']);
    $orgB = Organization::factory()->create(['slug' => 'org-b']);
    $customerUser = User::factory()->create();

    foreach ([$orgA, $orgB] as $org) {
        $schedule = Schedule::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $host->id,
        ]);
        $eventType = EventType::factory()->create([
            'organization_id' => $org->id,
            'host_user_id' => $host->id,
            'schedule_id' => $schedule->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $customerUser->id,
        ]);

        Booking::factory()->create([
            'organization_id' => $org->id,
            'event_type_id' => $eventType->id,
            'host_user_id' => $host->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    $bookingA = Booking::query()->where('organization_id', $orgA->id)->firstOrFail();
    $bookingB = Booking::query()->where('organization_id', $orgB->id)->firstOrFail();

    $tokenA = customerAccessToken($customerUser, $orgA->id);

    $listA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
        ->getJson('/api/v1/customer/bookings');
    $listA->assertOk()
        ->assertJsonStructure(['items', 'total', 'page', 'limit', 'pageCount']);
    expect(collect($listA->json('items'))->pluck('id'))->toContain($bookingA->id)
        ->not->toContain($bookingB->id);

    $this->withHeader('Authorization', 'Bearer '.$tokenA)
        ->getJson("/api/v1/customer/bookings/{$bookingB->uid}")
        ->assertNotFound();

    $switch = $this->withHeader('Authorization', 'Bearer '.$tokenA)
        ->postJson("/api/v1/customer/businesses/{$orgB->id}/switch");
    $switch->assertOk()
        ->assertJsonPath('data.customerOrgId', $orgB->id);

    $tokenB = (string) $switch->json('data.accessToken');
    $claims = Jwt::decode($tokenB);
    expect($claims['customerOrgId'])->toBe($orgB->id);

    $listB = $this->withHeader('Authorization', 'Bearer '.$tokenB)
        ->getJson('/api/v1/customer/bookings');
    $listB->assertOk();
    expect(collect($listB->json('items'))->pluck('id'))->toContain($bookingB->id)
        ->not->toContain($bookingA->id);

    $strangerOrg = Organization::factory()->create();
    $this->withHeader('Authorization', 'Bearer '.$tokenB)
        ->postJson("/api/v1/customer/businesses/{$strangerOrg->id}/switch")
        ->assertNotFound();
});
