<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'role' => OrganizationRole::Member->value,
            'token_hash' => hash('sha256', Str::random(48)),
            'invited_by_id' => User::factory(),
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
        ];
    }
}
