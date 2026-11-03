<?php

namespace App\Enums;

enum BookingSource: string
{
    case Hosted = 'HOSTED';
    case Embed = 'EMBED';
    case Api = 'API';
    case Dashboard = 'DASHBOARD';
}
