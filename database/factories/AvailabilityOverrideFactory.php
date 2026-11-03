<?php

namespace Database\Factories;

use App\Models\AvailabilityOverride;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityOverride>
 */
class AvailabilityOverrideFactory extends Factory
{
    protected $model = AvailabilityOverride::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'date' => now()->toDateString(),
            'is_unavailable' => false,
            'start_minute' => 600,
            'end_minute' => 720,
        ];
    }
}
