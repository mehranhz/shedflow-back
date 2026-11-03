<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'ACTIVE';
    case Disabled = 'DISABLED';
}
