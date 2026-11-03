<?php

namespace App\Jobs;

use App\Enums\EmailTemplate;
use App\Services\BookingReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBookingReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $bookingId,
        public EmailTemplate $template,
        public string $startAtIso,
    ) {}

    public function handle(BookingReminderService $reminders): void
    {
        $reminders->sendIfDue($this->bookingId, $this->template, $this->startAtIso);
    }
}
