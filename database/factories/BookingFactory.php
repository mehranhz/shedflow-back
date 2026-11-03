<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->utc()->addDay()->startOfHour();

        return [
            'uid' => strtolower(Str::random(10)),
            'organization_id' => Organization::factory(),
            'event_type_id' => EventType::factory(),
            'host_user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(30),
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'UTC',
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::Hosted,
            'location_type' => LocationType::Link,
            'location_value' => null,
            'answers' => [],
            'metadata' => [],
        ];
    }
}
