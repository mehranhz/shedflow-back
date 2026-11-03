<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Queued = 'QUEUED';
    case Sent = 'SENT';
    case Failed = 'FAILED';
}
