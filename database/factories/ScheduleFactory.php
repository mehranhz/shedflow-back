<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'created_by' => fn (array $attrs) => $attrs['user_id'],
            'name' => 'Working hours',
            'timezone' => 'UTC',
            'is_default' => true,
            'is_public' => false,
        ];
    }
}
