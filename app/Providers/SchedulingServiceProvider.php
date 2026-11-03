<?php

namespace App\Providers;

use App\Services\BookingService;
use App\Services\EventTypeService;
use App\Services\Interface\BookingServiceInterface;
use App\Services\Interface\EventTypeServiceInterface;
use App\Services\Interface\PaymentReceiptServiceInterface;
use App\Services\Interface\ScheduleServiceInterface;
use App\Services\PaymentReceiptService;
use App\Services\ScheduleService;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->bind(EventTypeServiceInterface::class, EventTypeService::class);
        $this->app->bind(BookingServiceInterface::class, BookingService::class);
        $this->app->bind(PaymentReceiptServiceInterface::class, PaymentReceiptService::class);
    }
}
