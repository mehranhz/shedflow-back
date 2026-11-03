<?php

namespace App\Providers;

use App\Services\Interface\ScheduleServiceInterface;
use App\Services\ScheduleService;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
