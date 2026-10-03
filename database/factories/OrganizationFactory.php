<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "name" => fake()->word(3,true),
            "slug" => fake()->unique()->word(3,true),
            "timezone" => "UTC",
            "locale" => "en",
            "currency" => "USD",
            "brand_color" => fake()->hexColor(),
        ];
    }
}
