<?php

namespace App\DTOs;

use Illuminate\Notifications\Notifiable;

class EmailRecipient
{
    use Notifiable;

    public function __construct(public string $email) {}

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function getKey(): string
    {
        return $this->email;
    }
}
