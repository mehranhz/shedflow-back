<?php

use App\Enums\LocationType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\AvailabilityRule;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JwtTokenService;
use Carbon\CarbonImmutable;

test('owner can create event type and list slots publicly', function () {
    ['user' => $user, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $create = $this->withHeader('Authorization', bearerFor($user, $organization))
        ->postJson("/api/v1/organizations/{$organization->id}/event-types", [
            'slug' => 'intro-call',
            'title' => 'Intro Call',
            'durationMinutes' => 30,
            'locationType' => LocationType::Link->value,
            'locationValue' => 'https://meet.example.com/intro',
            'scheduleId' => $schedule->id,
            'minNoticeMinutes' => 0,
        ]);

    $create->assertCreated()
        ->assertJsonPath('slug', 'intro-call');

    $nextMonday = CarbonImmutable::now('UTC')->next('Monday')->setTime(10, 0);

    $slots = $this->getJson(
        "/api/v1/public/orgs/{$organization->slug}/event-types/intro-call/slots"
        .'?from='.urlencode($nextMonday->toIso8601String())
        .'&to='.urlencode($nextMonday->addDay()->toIso8601String())
        .'&tz=UTC'
    );

    $slots->assertOk();
    expect($slots->json('slots'))->not->toBeEmpty();
});

test('public booking create cancel flow works with action tokens', function () {
    ['user' => $user, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'slug' => 'demo',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
    ]);

    $invitee = User::factory()->create([
        'phone' => '+15559876543',
        'phone_verified_at' => now(),
    ]);

    $start = CarbonImmutable::now('UTC')->next('Tuesday')->setTime(11, 0);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'phone' => $invitee->phone,
        ],
        'source' => 'HOSTED',
    ]);

    $book->assertCreated()
        ->assertJsonPath('status', 'CONFIRMED');

    $uid = $book->json('uid');
    $cancelToken = $book->json('actionTokens.cancel');

    $cancel = $this->postJson("/api/v1/public/bookings/{$uid}/cancel", [
        'token' => $cancelToken,
        'reason' => 'changed plans',
    ]);

    $cancel->assertOk()
        ->assertJsonPath('status', 'CANCELLED');
});

test('public booking rejects unverified phone until otp is completed', function () {
    ['user' => $user, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'slug' => 'guest-demo',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
    ]);

    $start = CarbonImmutable::now('UTC')->next('Wednesday')->setTime(11, 0);

    $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => 'guest-demo',
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Walk-in Guest',
            'email' => 'walkin-guest@example.com',
            'phone' => '+15550009999',
        ],
        'source' => 'HOSTED',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invitee.phone']);

    $this->postJson('/api/v1/auth/request-otp', [
        'username' => '+15550009999',
    ])->assertOk();

    $code = (string) \App\Models\OTP::query()->where('username', '+15550009999')->value('code');

    $this->postJson('/api/v1/auth/verify-otp', [
        'username' => '+15550009999',
        'code' => $code,
    ])->assertOk();

    $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => 'guest-demo',
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Walk-in Guest',
            'email' => 'walkin-guest@example.com',
            'phone' => '+15550009999',
        ],
        'source' => 'HOSTED',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'CONFIRMED');
});

test('owner can enable requires payment receipt on event type and public show exposes it', function () {
    ['user' => $user, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $create = $this->withHeader('Authorization', bearerFor($user, $organization))
        ->postJson("/api/v1/organizations/{$organization->id}/event-types", [
            'slug' => 'paid-consult',
            'title' => 'Paid Consult',
            'durationMinutes' => 45,
            'locationType' => LocationType::Link->value,
            'locationValue' => 'https://meet.example.com/paid',
            'scheduleId' => $schedule->id,
            'requiresPaymentReceipt' => true,
            'paymentInstructions' => "Transfer 500,000 IRR to IBAN IR00.\nUpload the receipt.",
        ]);

    $create->assertCreated()
        ->assertJsonPath('slug', 'paid-consult')
        ->assertJsonPath('requiresPaymentReceipt', true)
        ->assertJsonPath('paymentInstructions', "Transfer 500,000 IRR to IBAN IR00.\nUpload the receipt.");

    $eventTypeId = $create->json('id');

    $update = $this->withHeader('Authorization', bearerFor($user, $organization))
        ->patchJson("/api/v1/organizations/{$organization->id}/event-types/{$eventTypeId}", [
            'paymentInstructions' => 'Pay cash at desk and upload photo.',
        ]);

    $update->assertOk()
        ->assertJsonPath('requiresPaymentReceipt', true)
        ->assertJsonPath('paymentInstructions', 'Pay cash at desk and upload photo.');

    $public = $this->getJson("/api/v1/public/orgs/{$organization->slug}/event-types/paid-consult");

    $public->assertOk()
        ->assertJsonPath('requiresPaymentReceipt', true)
        ->assertJsonPath('requiresConfirmation', false)
        ->assertJsonPath('paymentInstructions', 'Pay cash at desk and upload photo.')
        ->assertJsonMissingPath('hostUserId')
        ->assertJsonMissingPath('organizationId');
});

test('member can only update own hosted event type receipt settings', function () {
    ['user' => $owner, 'organization' => $organization, 'schedule' => $ownerSchedule] = seedOrgWithDefaultSchedule();

    $member = User::factory()->create();
    $member->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Member->value,
        'status' => MembershipStatus::Active->value,
    ]);
    $memberSchedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'created_by' => $member->id,
        'timezone' => 'UTC',
        'is_default' => true,
    ]);

    $ownerEvent = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $owner->id,
        'schedule_id' => $ownerSchedule->id,
        'slug' => 'owner-event',
    ]);
    $memberEvent = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $member->id,
        'schedule_id' => $memberSchedule->id,
        'slug' => 'member-event',
    ]);

    $memberToken = 'Bearer '.app(JwtTokenService::class)->signAccessToken(
        $member,
        $organization->id,
        OrganizationRole::Member,
    );

    $forbidden = $this->withHeader('Authorization', $memberToken)
        ->patchJson("/api/v1/organizations/{$organization->id}/event-types/{$ownerEvent->id}", [
            'requiresPaymentReceipt' => true,
        ]);
    $forbidden->assertNotFound();

    $allowed = $this->withHeader('Authorization', $memberToken)
        ->patchJson("/api/v1/organizations/{$organization->id}/event-types/{$memberEvent->id}", [
            'requiresPaymentReceipt' => true,
            'paymentInstructions' => 'Card transfer then upload.',
        ]);
    $allowed->assertOk()
        ->assertJsonPath('requiresPaymentReceipt', true)
        ->assertJsonPath('paymentInstructions', 'Card transfer then upload.');
});

test('schedule rules can be replaced by owner', function () {
    ['user' => $user, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $response = $this->withHeader('Authorization', bearerFor($user, $organization))
        ->putJson("/api/v1/organizations/{$organization->id}/schedules/{$schedule->id}/rules", [
            'rules' => [
                ['dayOfWeek' => 1, 'startMinute' => 600, 'endMinute' => 900],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('rules.0.startMinute', 600)
        ->assertJsonCount(1, 'rules');
});

test('frontend auth login register refresh issue jwt with org claim', function () {
    $register = $this->postJson('/api/auth/register', [
        'username' => '+15551234567',
        'password' => 'password123',
        'name' => 'Owner',
        'organizationName' => 'Acme',
        'timezone' => 'UTC',
    ]);

    $register->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.phone', '+15551234567')
        ->assertJsonPath('data.user.email', null);

    $access = $register->json('data.accessToken');
    $refresh = $register->json('data.refreshToken');
    expect($access)->toBeString()->toContain('.');

    $parts = explode('.', $access);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    expect($payload)->toHaveKeys(['sub', 'phone', 'orgId', 'role', 'exp']);
    expect($payload)->not->toHaveKey('email');
    expect($payload['phone'])->toBe('+15551234567');
    expect($payload['role'])->toBe('OWNER');

    $me = $this->withHeader('Authorization', 'Bearer '.$access)
        ->getJson('/api/v1/auth/me');
    $me->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.phone', '+15551234567')
        ->assertJsonPath('data.memberships.0.id', $payload['orgId'])
        ->assertJsonPath('data.activeOrganization.id', $payload['orgId'])
        ->assertJsonPath('data.email', null);

    $login = $this->postJson('/api/auth/login', [
        'username' => '+15551234567',
        'password' => 'password123',
    ]);
    $login->assertOk()->assertJsonPath('data.user.phone', '+15551234567');

    $rotated = $this->postJson('/api/auth/refresh', [
        'refreshToken' => $refresh,
        'organizationId' => $payload['orgId'],
    ]);
    $rotated->assertOk()
        ->assertJsonStructure(['accessToken', 'refreshToken']);
});
