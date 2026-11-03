<?php

namespace Database\Seeders;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoPersonaSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner@shedflow.dev';

    public const CUSTOMER_EMAIL = 'customer@shedflow.dev';

    public const DEMO_PASSWORD = 'Password123!';

    public const ORG_SLUG = 'acme';

    public function run(): void
    {
        $password = Hash::make(self::DEMO_PASSWORD);

        $owner = User::query()->updateOrCreate(
            ['email' => self::OWNER_EMAIL],
            [
                'name' => 'Acme Owner',
                'phone' => '+15550001001',
                'phone_verified_at' => now(),
                'password' => $password,
                'timezone' => 'America/New_York',
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        $organization = Organization::query()->updateOrCreate(
            ['slug' => self::ORG_SLUG],
            [
                'name' => 'Acme',
                'timezone' => 'America/New_York',
                'locale' => 'en',
                'currency' => 'USD',
                'brand_color' => '#0069ff',
                'platform_plan' => 'PRO',
            ],
        );

        $organization->users()->syncWithoutDetaching([
            $owner->id => [
                'type' => OrganizationRole::Owner->value,
                'status' => MembershipStatus::Active->value,
            ],
        ]);

        $schedule = Schedule::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'is_default' => true,
            ],
            [
                'created_by' => $owner->id,
                'name' => 'Default',
                'timezone' => 'America/New_York',
            ],
        );

        AvailabilityRule::query()->where('schedule_id', $schedule->id)->delete();
        foreach ([1, 2, 3, 4, 5] as $day) {
            AvailabilityRule::query()->create([
                'schedule_id' => $schedule->id,
                'day_of_week' => $day,
                'start_minute' => 540,
                'end_minute' => 1020,
            ]);
        }

        EventType::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => 'intro',
            ],
            [
                'host_user_id' => $owner->id,
                'schedule_id' => $schedule->id,
                'title' => 'Intro call',
                'description' => '30-minute intro (free).',
                'duration_minutes' => 30,
                'location_type' => LocationType::Link,
                'location_value' => 'https://meet.example.com/intro',
                'min_notice_minutes' => 0,
                'cancellation_notice_hours' => 24,
                'reschedule_notice_hours' => 24,
                'is_active' => true,
                'is_hidden' => false,
            ],
        );

        $customerUser = User::query()->updateOrCreate(
            ['email' => self::CUSTOMER_EMAIL],
            [
                'name' => 'Demo Customer',
                'phone' => '+15550001002',
                'password' => $password,
                'timezone' => 'America/New_York',
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );

        $customer = Customer::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => self::CUSTOMER_EMAIL,
            ],
            [
                'user_id' => $customerUser->id,
                'name' => 'Demo Customer',
                'phone' => null,
                'timezone' => 'America/New_York',
            ],
        );

        $intro = EventType::query()
            ->where('organization_id', $organization->id)
            ->where('slug', 'intro')
            ->firstOrFail();

        $start = CarbonImmutable::now('UTC')->next('Monday')->setTime(15, 0);

        Booking::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'event_type_id' => $intro->id,
                'start_at' => $start,
            ],
            [
                'uid' => strtolower(Str::random(10)),
                'host_user_id' => $owner->id,
                'end_at' => $start->addMinutes(30),
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'timezone' => 'America/New_York',
                'status' => BookingStatus::Confirmed,
                'source' => BookingSource::Hosted,
                'location_type' => $intro->location_type,
                'location_value' => $intro->location_value,
                'answers' => [],
                'metadata' => [],
            ],
        );

        $this->command?->info('Demo personas seeded:');
        $this->command?->info('  owner:    '.self::OWNER_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('  customer: '.self::CUSTOMER_EMAIL.' / '.self::DEMO_PASSWORD);
        $this->command?->info('  org:      /'.self::ORG_SLUG.' (event: intro)');
    }
}
