<?php

namespace App\Enums;

enum SignedActionPurpose: string
{
    case Cancel = 'cancel';
    case Reschedule = 'reschedule';
    case Manage = 'manage';
}
