<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AvailabilityService
{
    private const MAX_RANGE_DAYS = 31;

    private const MAX_SLOTS = 500;

    /**
     * @return array{timezone:string,stale:bool,truncated:bool,slots:list<array{startAt:string,endAt:string,startLocal:string,endLocal:string}>}
     */
    public function listSlots(
        string $organizationId,
        string $eventTypeIdOrSlug,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        string $inviteeTimeZone,
    ): array {
        $this->requireTimeZone($inviteeTimeZone);

        if ($rangeEnd->lessThanOrEqualTo($rangeStart)) {
            return ['timezone' => $inviteeTimeZone, 'stale' => false, 'truncated' => false, 'slots' => []];
        }

        if ($rangeStart->diffInDays($rangeEnd) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => ['Range must be at most 31 days'],
            ]);
        }

        $eventType = EventType::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($eventTypeIdOrSlug) {
                $q->where('slug', $eventTypeIdOrSlug)->orWhere('id', $eventTypeIdOrSlug);
            })
            ->where('is_active', true)
            ->first();

        if (! $eventType) {
            throw new NotFoundHttpException('Not found');
        }

        $schedule = Schedule::query()
            ->where('organization_id', $organizationId)
            ->whereKey($eventType->schedule_id)
            ->with(['availabilityRules', 'availabilityOverrides'])
            ->first();

        if (! $schedule) {
            throw new NotFoundHttpException('Not found');
        }

        $now = CarbonImmutable::now('UTC');
        $minStart = $now->addMinutes($eventType->min_notice_minutes);
        $rangeStart = $rangeStart->lessThan($minStart) ? $minStart : $rangeStart;

        $scheduleToday = $now->timezone($schedule->timezone)->startOfDay();
        $maxEnd = $scheduleToday->addDays($eventType->max_days_ahead);
        $rangeEnd = $rangeEnd->greaterThan($maxEnd) ? $maxEnd : $rangeEnd;

        if ($rangeEnd->lessThanOrEqualTo($rangeStart)) {
            return ['timezone' => $inviteeTimeZone, 'stale' => false, 'truncated' => false, 'slots' => []];
        }

        $overrideByDate = [];
        foreach ($schedule->availabilityOverrides as $override) {
            $overrideByDate[$override->date->format('Y-m-d')] = $override;
        }

        $busy = $this->loadBusy(
            $eventType->host_user_id,
            $rangeStart->subMinutes(max($eventType->buffer_before_minutes, $eventType->buffer_after_minutes)),
            $rangeEnd->addMinutes(max($eventType->buffer_before_minutes, $eventType->buffer_after_minutes)),
        );

        $stepMinutes = $eventType->slot_interval_minutes > 0
            ? $eventType->slot_interval_minutes
            : $eventType->duration_minutes;
        $duration = $eventType->duration_minutes;
        $slots = [];
        $truncated = false;

        $cursor = $rangeStart->timezone($schedule->timezone)->startOfDay();
        $localEnd = $rangeEnd->timezone($schedule->timezone);

        while ($cursor->lessThanOrEqualTo($localEnd)) {
            $dateKey = $cursor->format('Y-m-d');
            $dayOfWeek = (int) $cursor->dayOfWeek;
            $override = $overrideByDate[$dateKey] ?? null;

            $windows = [];
            if ($override) {
                if (! $override->is_unavailable && $override->start_minute !== null && $override->end_minute !== null) {
                    $windows[] = [
                        'startMinute' => $override->start_minute,
                        'endMinute' => $override->end_minute,
                    ];
                }
            } else {
                foreach ($schedule->availabilityRules as $rule) {
                    if ((int) $rule->day_of_week === $dayOfWeek) {
                        $windows[] = [
                            'startMinute' => $rule->start_minute,
                            'endMinute' => $rule->end_minute,
                        ];
                    }
                }
            }

            $dayCap = $eventType->daily_cap;
            $dayCount = $this->countBookingsForLocalDay($eventType->id, $schedule->timezone, $dateKey);
            if ($dayCap !== null && $dayCount >= $dayCap) {
                $cursor = $cursor->addDay();

                continue;
            }

            $bookedThatDay = $dayCount;
            foreach ($windows as $window) {
                $windowStart = $this->localWallTimeToUtc($dateKey, $window['startMinute'], $schedule->timezone);
                $windowEnd = $this->localWallTimeToUtc($dateKey, $window['endMinute'], $schedule->timezone);
                if (! $windowStart || ! $windowEnd) {
                    continue;
                }

                for ($t = $windowStart; $t->addMinutes($duration)->lessThanOrEqualTo($windowEnd); $t = $t->addMinutes($stepMinutes)) {
                    if ($t->lessThan($minStart) || $t->lessThan($rangeStart) || $t->greaterThanOrEqualTo($rangeEnd)) {
                        continue;
                    }
                    if ($dayCap !== null && $bookedThatDay >= $dayCap) {
                        break;
                    }

                    $end = $t->addMinutes($duration);
                    $occupiedStart = $t->subMinutes($eventType->buffer_before_minutes);
                    $occupiedEnd = $end->addMinutes($eventType->buffer_after_minutes);
                    if ($this->overlapsBusy($occupiedStart, $occupiedEnd, $busy)) {
                        continue;
                    }

                    $slots[] = [
                        'startAt' => $t->utc()->toIso8601String(),
                        'endAt' => $end->utc()->toIso8601String(),
                        'startLocal' => $t->timezone($inviteeTimeZone)->toIso8601String(),
                        'endLocal' => $end->timezone($inviteeTimeZone)->toIso8601String(),
                    ];
                    $bookedThatDay++;

                    if (count($slots) >= self::MAX_SLOTS) {
                        $truncated = true;
                        break 2;
                    }
                }
            }

            $cursor = $cursor->addDay();
        }

        return [
            'timezone' => $inviteeTimeZone,
            'stale' => false,
            'truncated' => $truncated,
            'slots' => $slots,
        ];
    }

    public function listSlotsForOrgSlug(
        string $orgSlug,
        string $eventSlug,
        ?string $from,
        ?string $to,
        ?string $tz,
    ): array {
        $org = Organization::query()->where('slug', $orgSlug)->first();
        if (! $org) {
            throw new NotFoundHttpException('Not found');
        }

        $rangeStart = $from ? CarbonImmutable::parse($from) : CarbonImmutable::now('UTC');
        $rangeEnd = $to ? CarbonImmutable::parse($to) : $rangeStart->addDays(7);

        return $this->listSlots(
            $org->id,
            $eventSlug,
            $rangeStart,
            $rangeEnd,
            $tz ?? $org->timezone,
        );
    }

    /**
     * @return list<array{start:CarbonImmutable,end:CarbonImmutable}>
     */
    private function loadBusy(string $hostUserId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        app(BookingService::class)->expireStalePaymentHolds($hostUserId);

        return Booking::query()
            ->where('host_user_id', $hostUserId)
            ->occupyingSlot()
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->get()
            ->map(fn (Booking $booking) => [
                'start' => $booking->start_at->toImmutable()->subMinutes($booking->buffer_before_minutes),
                'end' => $booking->end_at->toImmutable()->addMinutes($booking->buffer_after_minutes),
            ])
            ->all();
    }

    private function countBookingsForLocalDay(string $eventTypeId, string $timezone, string $dateKey): int
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $dateKey.' 00:00:00', $timezone)->utc();
        $end = $start->timezone($timezone)->addDay()->utc();

        return Booking::query()
            ->where('event_type_id', $eventTypeId)
            ->occupyingSlot()
            ->where('start_at', '>=', $start)
            ->where('start_at', '<', $end)
            ->count();
    }

    /**
     * @param  list<array{start:CarbonImmutable,end:CarbonImmutable}>  $busy
     */
    private function overlapsBusy(CarbonImmutable $start, CarbonImmutable $end, array $busy): bool
    {
        foreach ($busy as $block) {
            if ($start->lessThan($block['end']) && $end->greaterThan($block['start'])) {
                return true;
            }
        }

        return false;
    }

    private function localWallTimeToUtc(string $dateKey, int $minuteOfDay, string $timezone): ?CarbonImmutable
    {
        $date = $dateKey;
        $minute = $minuteOfDay;
        if ($minute === 1440) {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $dateKey, $timezone)->addDay()->format('Y-m-d');
            $minute = 0;
        }
        if ($minute < 0 || $minute > 1439) {
            return null;
        }

        $hours = intdiv($minute, 60);
        $minutes = $minute % 60;

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $hours, $minutes),
                $timezone,
            )->utc();
        } catch (\Exception) {
            return null;
        }
    }

    private function requireTimeZone(string $timezone): void
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'tz' => ['Invalid IANA timezone'],
            ]);
        }
    }
}
