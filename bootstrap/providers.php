<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\InvitationServiceProvider;
use App\Providers\OrganizationServiceProvider;
use App\Providers\ProfileServiceProvider;
use App\Providers\ScheduleServiceProvider;
use App\Providers\SchedulingServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    InvitationServiceProvider::class,
    OrganizationServiceProvider::class,
    ProfileServiceProvider::class,
    ScheduleServiceProvider::class,
    SchedulingServiceProvider::class,
];
