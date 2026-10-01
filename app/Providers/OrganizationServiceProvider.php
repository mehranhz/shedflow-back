<?php

namespace App\Providers;

use App\Services\Interface\OrganizationServiceInterface;
use App\Services\OrganizationService;
use Illuminate\Support\ServiceProvider;

class OrganizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationServiceInterface::class, OrganizationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
