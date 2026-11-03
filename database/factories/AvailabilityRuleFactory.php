<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    protected $model = AvailabilityRule::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'day_of_week' => 1,
            'start_minute' => 540,
            'end_minute' => 1020,
        ];
    }
}
