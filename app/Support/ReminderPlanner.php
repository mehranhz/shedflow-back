<?php

namespace App\Support;

use App\Enums\EmailTemplate;
use Carbon\CarbonInterface;

final class ReminderPlanner
{
    public const REMINDER_24H_SECONDS = 86400;

    public const REMINDER_1H_SECONDS = 3600;

    /**
     * @return list<array{template: EmailTemplate, runAt: CarbonInterface, startAtIso: string}>
     */
    public static function plan(string $bookingId, CarbonInterface $startAt, CarbonInterface $now): array
    {
        $startMs = $startAt->getTimestamp();
        $nowMs = $now->getTimestamp();
        $untilStart = $startMs - $nowMs;
        $startAtIso = $startAt->toIso8601String();
        $planned = [];

        if ($untilStart > self::REMINDER_24H_SECONDS) {
            $planned[] = [
                'template' => EmailTemplate::Reminder24h,
                'runAt' => $startAt->copy()->subSeconds(self::REMINDER_24H_SECONDS),
                'startAtIso' => $startAtIso,
            ];
        }

        if ($untilStart > self::REMINDER_1H_SECONDS) {
            $planned[] = [
                'template' => EmailTemplate::Reminder1h,
                'runAt' => $startAt->copy()->subSeconds(self::REMINDER_1H_SECONDS),
                'startAtIso' => $startAtIso,
            ];
        }

        return $planned;
    }
}
