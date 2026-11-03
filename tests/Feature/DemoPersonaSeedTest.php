<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DemoPersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo persona seeder is idempotent and links customer user', function () {
    $this->seed(DemoPersonaSeeder::class);
    $this->seed(DemoPersonaSeeder::class);

    $customerUser = User::query()->where('email', DemoPersonaSeeder::CUSTOMER_EMAIL)->firstOrFail();
    $owner = User::query()->where('email', DemoPersonaSeeder::OWNER_EMAIL)->firstOrFail();

    expect(User::query()->where('email', DemoPersonaSeeder::CUSTOMER_EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', DemoPersonaSeeder::OWNER_EMAIL)->count())->toBe(1);

    $customer = Customer::query()
        ->where('email', DemoPersonaSeeder::CUSTOMER_EMAIL)
        ->where('user_id', $customerUser->id)
        ->first();

    expect($customer)->not->toBeNull()
        ->and($owner->organizations()->where('slug', DemoPersonaSeeder::ORG_SLUG)->exists())->toBeTrue();
});

test('seeded customer can log in via customer auth', function () {
    $this->seed(DemoPersonaSeeder::class);

    $login = $this->postJson('/api/v1/customer/auth/login', [
        'email' => DemoPersonaSeeder::CUSTOMER_EMAIL,
        'password' => DemoPersonaSeeder::DEMO_PASSWORD,
    ]);

    $login->assertOk()
        ->assertJsonPath('data.actorType', 'customer')
        ->assertJsonPath('data.businesses.0.slug', DemoPersonaSeeder::ORG_SLUG);
});
