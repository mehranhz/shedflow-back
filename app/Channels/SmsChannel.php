<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $phone = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($notification)
            : ($notifiable->phone ?? 'unknown');

        logger('send: '.$message."\n to ".$phone);
    }
}
