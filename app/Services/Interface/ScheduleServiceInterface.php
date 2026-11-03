<?php

namespace App\Services\Interface;

use App\Models\Schedule;
use App\Models\User;

interface ScheduleServiceInterface
{
    /**
     * @return list<Schedule>
     */
    public function listForOrganization(User $user, string $organizationId): array;

    public function getForOrganization(User $user, string $organizationId, string $scheduleId): Schedule;

    public function create(User $user, string $organizationId, array $input): Schedule;

    public function createDefaultSchedule(User $user, string $organizationId): Schedule;

    public function update(User $user, string $organizationId, string $scheduleId, array $input): Schedule;

    public function delete(User $user, string $organizationId, string $scheduleId): void;

    /**
     * @param  list<array{dayOfWeek:int,startMinute:int,endMinute:int}>  $rules
     */
    public function replaceRules(User $user, string $organizationId, string $scheduleId, array $rules): Schedule;

    public function upsertOverride(User $user, string $organizationId, string $scheduleId, string $date, array $input): Schedule;

    public function deleteOverride(User $user, string $organizationId, string $scheduleId, string $date): void;
}
