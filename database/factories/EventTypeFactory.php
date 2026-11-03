<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventType>
 */
class EventTypeFactory extends Factory
{
    protected $model = EventType::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'host_user_id' => User::factory(),
            'schedule_id' => Schedule::factory(),
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'title' => fake()->sentence(3),
            'description' => '',
            'duration_minutes' => 30,
            'location_type' => LocationType::Link,
            'location_value' => 'https://meet.example.com',
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'min_notice_minutes' => 0,
            'max_days_ahead' => 60,
            'slot_interval_minutes' => 0,
            'daily_cap' => null,
            'requires_confirmation' => false,
            'requires_payment_receipt' => false,
            'payment_instructions' => null,
            'cancellation_notice_hours' => 24,
            'reschedule_notice_hours' => 24,
            'questions' => [],
            'is_active' => true,
            'is_hidden' => false,
        ];
    }
}
