<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\AvailabilityOverride;
use App\Models\AvailabilityRule;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Interface\ScheduleServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ScheduleService implements ScheduleServiceInterface
{
    private const DEFAULT_RULES = [
        ['dayOfWeek' => 1, 'startMinute' => 540, 'endMinute' => 1020],
        ['dayOfWeek' => 2, 'startMinute' => 540, 'endMinute' => 1020],
        ['dayOfWeek' => 3, 'startMinute' => 540, 'endMinute' => 1020],
        ['dayOfWeek' => 4, 'startMinute' => 540, 'endMinute' => 1020],
        ['dayOfWeek' => 5, 'startMinute' => 540, 'endMinute' => 1020],
    ];

    public function listForOrganization(User $user, string $organizationId): array
    {
        $role = $this->requireMembership($user, $organizationId);

        $query = Schedule::query()
            ->where('organization_id', $organizationId)
            ->with(['availabilityRules', 'availabilityOverrides']);

        if (! $role->isStaff()) {
            $query->where('user_id', $user->id);
        }

        return $query->orderBy('name')->get()->all();
    }

    public function getForOrganization(User $user, string $organizationId, string $scheduleId): Schedule
    {
        return $this->requireWritable($user, $organizationId, $scheduleId, write: false);
    }

    public function create(User $user, string $organizationId, array $input): Schedule
    {
        $role = $this->requireMembership($user, $organizationId);
        $hostUserId = $this->resolveHostUserId($user, $organizationId, $role, $input['hostUserId'] ?? null);
        $timezone = $this->requireTimeZone($input['timezone'] ?? 'UTC');
        $isDefault = (bool) ($input['isDefault'] ?? false);

        return DB::transaction(function () use ($user, $organizationId, $hostUserId, $timezone, $isDefault, $input) {
            if ($isDefault) {
                $this->clearDefaultForHost($organizationId, $hostUserId);
            }

            return Schedule::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $hostUserId,
                'created_by' => $user->id,
                'name' => trim((string) $input['name']),
                'timezone' => $timezone,
                'is_default' => $isDefault,
                'is_public' => (bool) ($input['isPublic'] ?? false),
            ])->load(['availabilityRules', 'availabilityOverrides']);
        });
    }

    public function createDefaultSchedule(User $user, string $organizationId): Schedule
    {
        $this->requireMembership($user, $organizationId);
        $organization = Organization::query()->findOrFail($organizationId);

        return DB::transaction(function () use ($user, $organization) {
            $this->clearDefaultForHost($organization->id, $user->id);

            $schedule = Schedule::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'created_by' => $user->id,
                'name' => 'Working hours',
                'timezone' => $organization->timezone ?: 'UTC',
                'is_default' => true,
                'is_public' => false,
            ]);

            $this->writeRules($schedule, self::DEFAULT_RULES);

            return $schedule->load(['availabilityRules', 'availabilityOverrides']);
        });
    }

    public function update(User $user, string $organizationId, string $scheduleId, array $input): Schedule
    {
        $schedule = $this->requireWritable($user, $organizationId, $scheduleId, write: true);

        return DB::transaction(function () use ($schedule, $organizationId, $input) {
            if (array_key_exists('name', $input)) {
                $schedule->name = trim((string) $input['name']);
            }
            if (array_key_exists('timezone', $input)) {
                $schedule->timezone = $this->requireTimeZone((string) $input['timezone']);
            }
            if (array_key_exists('isPublic', $input)) {
                $schedule->is_public = (bool) $input['isPublic'];
            }
            if (array_key_exists('isDefault', $input) && $input['isDefault']) {
                $this->clearDefaultForHost($organizationId, $schedule->user_id, $schedule->id);
                $schedule->is_default = true;
            } elseif (array_key_exists('isDefault', $input)) {
                $schedule->is_default = (bool) $input['isDefault'];
            }

            $schedule->save();

            return $schedule->load(['availabilityRules', 'availabilityOverrides']);
        });
    }

    public function delete(User $user, string $organizationId, string $scheduleId): void
    {
        $schedule = $this->requireWritable($user, $organizationId, $scheduleId, write: true);
        $schedule->delete();
    }

    public function replaceRules(User $user, string $organizationId, string $scheduleId, array $rules): Schedule
    {
        $schedule = $this->requireWritable($user, $organizationId, $scheduleId, write: true);
        $this->assertRulesValid($rules);

        return DB::transaction(function () use ($schedule, $rules) {
            $this->writeRules($schedule, $rules);

            return $schedule->load(['availabilityRules', 'availabilityOverrides']);
        });
    }

    public function upsertOverride(User $user, string $organizationId, string $scheduleId, string $date, array $input): Schedule
    {
        $schedule = $this->requireWritable($user, $organizationId, $scheduleId, write: true);
        $this->assertOverrideValid($date, $input);

        AvailabilityOverride::query()->updateOrCreate(
            ['schedule_id' => $schedule->id, 'date' => $date],
            [
                'is_unavailable' => (bool) ($input['isUnavailable'] ?? false),
                'start_minute' => $input['startMinute'] ?? null,
                'end_minute' => $input['endMinute'] ?? null,
            ],
        );

        return $schedule->load(['availabilityRules', 'availabilityOverrides']);
    }

    public function deleteOverride(User $user, string $organizationId, string $scheduleId, string $date): void
    {
        $schedule = $this->requireWritable($user, $organizationId, $scheduleId, write: true);
        AvailabilityOverride::query()
            ->where('schedule_id', $schedule->id)
            ->whereDate('date', $date)
            ->delete();
    }

    /**
     * @param  list<array{dayOfWeek:int,startMinute:int,endMinute:int}>  $rules
     */
    private function writeRules(Schedule $schedule, array $rules): void
    {
        AvailabilityRule::query()->where('schedule_id', $schedule->id)->delete();

        foreach ($rules as $rule) {
            AvailabilityRule::query()->create([
                'schedule_id' => $schedule->id,
                'day_of_week' => $rule['dayOfWeek'],
                'start_minute' => $rule['startMinute'],
                'end_minute' => $rule['endMinute'],
            ]);
        }
    }

    private function clearDefaultForHost(string $organizationId, string $hostUserId, ?string $exceptId = null): void
    {
        $query = Schedule::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $hostUserId)
            ->where('is_default', true);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    private function requireWritable(User $user, string $organizationId, string $scheduleId, bool $write): Schedule
    {
        $role = $this->requireMembership($user, $organizationId);

        $schedule = Schedule::query()
            ->where('organization_id', $organizationId)
            ->where('id', $scheduleId)
            ->with(['availabilityRules', 'availabilityOverrides'])
            ->first();

        if (! $schedule) {
            throw new NotFoundHttpException('Not found');
        }

        if (! $role->isStaff() && $schedule->user_id !== $user->id) {
            if ($write) {
                throw new AccessDeniedHttpException('Insufficient role');
            }

            throw new NotFoundHttpException('Not found');
        }

        return $schedule;
    }

    private function requireMembership(User $user, string $organizationId): OrganizationRole
    {
        $membership = $user->organizations()
            ->where('organizations.id', $organizationId)
            ->first();

        if (! $membership) {
            throw new AccessDeniedHttpException('Not a member of this organization');
        }

        $type = (string) $membership->membership->type;

        return OrganizationRole::tryFrom($type) ?? OrganizationRole::Member;
    }

    private function resolveHostUserId(User $actor, string $organizationId, OrganizationRole $role, ?string $requested): string
    {
        if (! $role->isStaff()) {
            if ($requested && $requested !== $actor->id) {
                throw new AccessDeniedHttpException('Insufficient role');
            }

            return $actor->id;
        }

        $hostUserId = $requested ?? $actor->id;
        $exists = User::query()
            ->whereKey($hostUserId)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organizationId))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'hostUserId' => ['Host must be an organization member'],
            ]);
        }

        return $hostUserId;
    }

    private function requireTimeZone(string $timezone): string
    {
        if ($timezone === 'UTC') {
            return $timezone;
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'timezone' => ['Invalid IANA timezone'],
            ]);
        }

        return $timezone;
    }

    /**
     * @param  list<array{dayOfWeek:int,startMinute:int,endMinute:int}>  $rules
     */
    private function assertRulesValid(array $rules): void
    {
        $byDay = [];

        foreach ($rules as $rule) {
            $day = (int) $rule['dayOfWeek'];
            $start = (int) $rule['startMinute'];
            $end = (int) $rule['endMinute'];

            if ($day < 0 || $day > 6) {
                throw ValidationException::withMessages([
                    'dayOfWeek' => ['dayOfWeek must be 0–6'],
                ]);
            }

            if ($start < 0 || $end > 1440 || $start >= $end) {
                throw ValidationException::withMessages([
                    'rules' => ['Each rule must satisfy 0 ≤ start < end ≤ 1440'],
                ]);
            }

            $byDay[$day][] = ['startMinute' => $start, 'endMinute' => $end];
        }

        foreach ($byDay as $dayRules) {
            usort($dayRules, fn ($a, $b) => $a['startMinute'] <=> $b['startMinute']);
            for ($i = 1; $i < count($dayRules); $i++) {
                if ($dayRules[$i]['startMinute'] < $dayRules[$i - 1]['endMinute']) {
                    throw ValidationException::withMessages([
                        'rules' => ['Overlapping availability windows for the same day'],
                    ]);
                }
            }
        }
    }

    private function assertOverrideValid(string $date, array $input): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                'date' => ['Date must be YYYY-MM-DD'],
            ]);
        }

        if (! empty($input['isUnavailable'])) {
            return;
        }

        $start = $input['startMinute'] ?? null;
        $end = $input['endMinute'] ?? null;

        if ($start === null || $end === null || $start < 0 || $end > 1440 || $start >= $end) {
            throw ValidationException::withMessages([
                'startMinute' => ['Available overrides require startMinute < endMinute'],
            ]);
        }
    }
}
