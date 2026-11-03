<?php

namespace App\Console\Commands;

use App\Services\Interface\BookingServiceInterface;
use Illuminate\Console\Command;

class ExpirePaymentHoldsCommand extends Command
{
    protected $signature = 'bookings:expire-payment-holds';

    protected $description = 'Mark PENDING_PAYMENT bookings expired when hold_expires_at has passed (T-020 / T-026)';

    public function handle(BookingServiceInterface $bookings): int
    {
        $count = $bookings->expireStalePaymentHolds();

        if ($count > 0) {
            $this->info("Expired {$count} payment hold(s).");
        }

        return self::SUCCESS;
    }
}
