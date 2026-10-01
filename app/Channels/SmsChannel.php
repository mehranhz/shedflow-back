<?php

namespace App\Channels;



use App\Notifications\JoinToOrganizationInvitation;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function send(object $notifiable, JoinToOrganizationInvitation $notification): void
    {
        $message = $notification->toSms($notifiable);

        logger("send: ".$message."\n to ".$notifiable->phone);
    }
}
