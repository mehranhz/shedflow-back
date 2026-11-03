<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'brand_color' => fake()->hexColor(),
            'platform_plan' => 'FREE',
            'settings' => [],
        ];
    }
}
