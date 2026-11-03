<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function seedOrgWithDefaultSchedule(): array
{
    $user = \App\Models\User::factory()->create();
    $organization = \App\Models\Organization::factory()->create(['timezone' => 'UTC']);
    $user->organizations()->attach($organization->id, [
        'type' => \App\Enums\OrganizationRole::Owner->value,
        'status' => \App\Enums\MembershipStatus::Active->value,
    ]);

    $schedule = \App\Models\Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'timezone' => 'UTC',
        'is_default' => true,
    ]);

    foreach ([1, 2, 3, 4, 5] as $day) {
        \App\Models\AvailabilityRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => $day,
            'start_minute' => 540,
            'end_minute' => 1020,
        ]);
    }

    return compact('user', 'organization', 'schedule');
}

function bearerFor(\App\Models\User $user, \App\Models\Organization $organization): string
{
    return 'Bearer '.app(\App\Services\JwtTokenService::class)->signAccessToken(
        $user,
        $organization->id,
        \App\Enums\OrganizationRole::Owner,
    );
}

function customerAccessToken(\App\Models\User $user, ?string $organizationId = null): string
{
    $jwt = app(\App\Services\JwtTokenService::class);
    if ($organizationId === null) {
        [$organizationId, $customerId] = $jwt->pickActiveCustomerOrg($user);
    } else {
        $customer = \App\Models\Customer::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();
        $customerId = $customer->id;
    }

    return $jwt->signAccessToken(
        $user,
        null,
        null,
        null,
        \App\Enums\ActorType::Customer,
        $organizationId,
        $customerId ?? null,
    );
}
