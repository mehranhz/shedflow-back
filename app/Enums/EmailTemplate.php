<?php

namespace App\Enums;

enum EmailTemplate: string
{
    case BookingConfirmedHost = 'booking-confirmed-host';
    case BookingConfirmedInvitee = 'booking-confirmed-invitee';
    case BookingPendingHost = 'booking-pending-host';
    case BookingCancelledHost = 'booking-cancelled-host';
    case BookingCancelledInvitee = 'booking-cancelled-invitee';
    case BookingRescheduledHost = 'booking-rescheduled-host';
    case BookingRescheduledInvitee = 'booking-rescheduled-invitee';
    case Reminder24h = 'reminder-24h';
    case Reminder1h = 'reminder-1h';
    case Reminder24hHost = 'reminder-24h-host';
    case Reminder24hInvitee = 'reminder-24h-invitee';
    case Reminder1hHost = 'reminder-1h-host';
    case Reminder1hInvitee = 'reminder-1h-invitee';
}
