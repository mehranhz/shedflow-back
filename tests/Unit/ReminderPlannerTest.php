<?php

use App\Enums\EmailTemplate;
use App\Support\ReminderPlanner;
use Carbon\CarbonImmutable;

test('plans 24h and 1h reminders when start is far enough away', function () {
    $now = CarbonImmutable::parse('2030-06-01T10:00:00Z');
    $startAt = $now->addDays(3);

    $planned = ReminderPlanner::plan('booking-1', $startAt, $now);

    expect($planned)->toHaveCount(2)
        ->and($planned[0]['template'])->toBe(EmailTemplate::Reminder24h)
        ->and($planned[0]['runAt']->equalTo($startAt->subSeconds(ReminderPlanner::REMINDER_24H_SECONDS)))->toBeTrue()
        ->and($planned[1]['template'])->toBe(EmailTemplate::Reminder1h)
        ->and($planned[1]['runAt']->equalTo($startAt->subSeconds(ReminderPlanner::REMINDER_1H_SECONDS)))->toBeTrue();
});

test('skips 24h reminder when booking starts within 24 hours', function () {
    $now = CarbonImmutable::parse('2030-06-01T10:00:00Z');
    $startAt = $now->addHours(2);

    $planned = ReminderPlanner::plan('booking-2', $startAt, $now);

    expect($planned)->toHaveCount(1)
        ->and($planned[0]['template'])->toBe(EmailTemplate::Reminder1h);
});

test('skips all reminders when booking starts within one hour', function () {
    $now = CarbonImmutable::parse('2030-06-01T10:00:00Z');
    $startAt = $now->addMinutes(45);

    expect(ReminderPlanner::plan('booking-3', $startAt, $now))->toBe([]);
});

test('includes startAt iso on each planned reminder for stale job detection', function () {
    $now = CarbonImmutable::parse('2030-06-01T10:00:00Z');
    $startAt = $now->addDays(2);

    $planned = ReminderPlanner::plan('booking-4', $startAt, $now);

    foreach ($planned as $item) {
        expect($item['startAtIso'])->toBe($startAt->toIso8601String());
    }
});
