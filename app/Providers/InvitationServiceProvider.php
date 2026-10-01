<?php

namespace App\Providers;

use App\Services\Interface\InvitationServiceInterface;
use App\Services\InvitationService;
use Illuminate\Support\ServiceProvider;

class InvitationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(InvitationServiceInterface::class, InvitationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
