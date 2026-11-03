<?php

use App\Enums\ActorType;
use App\Enums\BookingStatus;
use App\Mail\CustomerClaimMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerClaimToken;
use App\Models\EventType;
use App\Models\User;
use App\Support\Jwt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

test('customer register login and refresh issue customer actor session', function () {
    Mail::fake();

    $register = $this->postJson('/api/v1/customer/auth/register', [
        'email' => 'portal@example.com',
        'password' => 'Password123!',
        'name' => 'Portal User',
        'timezone' => 'America/New_York',
    ]);

    $register->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.actorType', ActorType::Customer->value)
        ->assertJsonPath('data.user.email', 'portal@example.com')
        ->assertJsonPath('data.businesses', []);

    Mail::assertSent(CustomerWelcomeMail::class);

    $refresh = (string) $register->json('data.refreshToken');

    $login = $this->postJson('/api/v1/customer/auth/login', [
        'email' => 'portal@example.com',
        'password' => 'Password123!',
    ]);

    $login->assertOk()
        ->assertJsonPath('data.actorType', ActorType::Customer->value);

    $claims = Jwt::decode((string) $login->json('data.accessToken'));
    expect($claims['actorType'])->toBe(ActorType::Customer->value)
        ->and($claims)->not->toHaveKey('orgId');

    $rotated = $this->postJson('/api/v1/auth/refresh', [
        'refreshToken' => $refresh,
    ]);

    $rotated->assertOk()->assertJsonStructure(['accessToken', 'refreshToken']);
    $rotatedClaims = Jwt::decode((string) $rotated->json('accessToken'));
    expect($rotatedClaims['actorType'])->toBe(ActorType::Customer->value);
});

test('customer login rejects staff-only users', function () {
    ['user' => $owner] = seedOrgWithDefaultSchedule();
    $owner->update([
        'email' => 'owner-only@example.com',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);

    $this->postJson('/api/v1/customer/auth/login', [
        'email' => 'owner-only@example.com',
        'password' => 'password',
    ])->assertUnauthorized();
});

test('claim request is enumeration-safe and sends mail only when customers exist', function () {
    Mail::fake();

    $this->postJson('/api/v1/customer/auth/claim', [
        'email' => 'nobody@example.com',
    ])->assertNoContent();

    Mail::assertNothingSent();
    expect(CustomerClaimToken::query()->count())->toBe(0);

    $organization = \App\Models\Organization::factory()->create();
    Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ada@example.com',
        'user_id' => null,
    ]);

    $this->postJson('/api/v1/customer/auth/claim', [
        'email' => 'ADA@EXAMPLE.COM',
    ])->assertNoContent();

    Mail::assertSent(CustomerClaimMail::class);
    expect(CustomerClaimToken::query()->count())->toBe(1);
});

test('guest book claim confirm lists booking and rejects reused token', function () {
    Mail::fake();

    ['user' => $host, 'organization' => $organization, 'schedule' => $schedule] = seedOrgWithDefaultSchedule();

    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $host->id,
        'schedule_id' => $schedule->id,
        'slug' => 'claim-demo',
        'duration_minutes' => 30,
        'min_notice_minutes' => 0,
        'is_active' => true,
        'is_hidden' => false,
    ]);

    $phoneUser = User::factory()->create([
        'phone' => '+15551112222',
        'phone_verified_at' => now(),
    ]);

    $start = CarbonImmutable::now('UTC')->next('Wednesday')->setTime(14, 0);

    $book = $this->postJson('/api/v1/public/bookings', [
        'orgSlug' => $organization->slug,
        'eventTypeSlug' => $eventType->slug,
        'startAt' => $start->toIso8601String(),
        'timezone' => 'UTC',
        'invitee' => [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => $phoneUser->phone,
        ],
        'source' => 'HOSTED',
    ]);

    $book->assertCreated();
    $bookingId = $book->json('id');

    $claimUrl = null;
    $this->postJson('/api/v1/customer/auth/claim', ['email' => 'ada@example.com'])
        ->assertNoContent();

    Mail::assertSent(CustomerClaimMail::class, function (CustomerClaimMail $mail) use (&$claimUrl) {
        $claimUrl = $mail->claimUrl;

        return str_contains($claimUrl, '/portal/claim?token=');
    });

    parse_str((string) parse_url((string) $claimUrl, PHP_URL_QUERY), $query);
    $rawToken = $query['token'] ?? '';

    $confirm = $this->postJson('/api/v1/customer/auth/claim/confirm', [
        'token' => $rawToken,
    ]);

    $confirm->assertOk()
        ->assertJsonPath('data.actorType', ActorType::Customer->value)
        ->assertJsonPath('data.businesses.0.organizationId', $organization->id)
        ->assertJsonPath('data.businesses.0.customerId', fn ($id) => is_string($id) && $id !== '');

    $access = (string) $confirm->json('data.accessToken');

    $listed = $this->withHeader('Authorization', 'Bearer '.$access)
        ->getJson('/api/v1/me/bookings');

    $listed->assertOk();
    expect(collect($listed->json())->pluck('id'))->toContain($bookingId);

    expect(
        Customer::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', ['ada@example.com'])
            ->value('user_id'),
    )->not->toBeNull();

    $this->postJson('/api/v1/customer/auth/claim/confirm', [
        'token' => $rawToken,
    ])->assertUnauthorized();
});

test('register links existing customer rows by email', function () {
    Mail::fake();

    $organization = \App\Models\Organization::factory()->create();
    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'linked@example.com',
        'user_id' => null,
    ]);

    $this->postJson('/api/v1/customer/auth/register', [
        'email' => 'linked@example.com',
        'password' => 'Password123!',
    ])->assertCreated()
        ->assertJsonPath('data.businesses.0.customerId', $customer->id);

    expect($customer->fresh()->user_id)->not->toBeNull();
});
