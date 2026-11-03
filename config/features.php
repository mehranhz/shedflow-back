<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer portal (Wave 10)
    |--------------------------------------------------------------------------
    |
    | When enabled, booking notifications include HTTPS deep links to
    | /portal/appointments/{uid} alongside signed manage URLs.
    |
    */
    'customer_portal' => env('CUSTOMER_PORTAL', env('APP_ENV', 'production') === 'local'),

];
