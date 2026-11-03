<?php

$secret = env('JWT_SECRET') ?: env('APP_KEY');

return [
    'secret' => $secret,
    'ttl' => (int) env('JWT_TTL', 900),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 30),
    'issuer' => env('JWT_ISS', env('APP_URL', 'http://localhost')),
];
