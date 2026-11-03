<?php

namespace App\DTOs;

use Illuminate\Notifications\Notifiable;

class ContactDTO
{
    use Notifiable;

    public function __construct(public string $phone) {}

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }

    public function getKey(): string
    {
        return $this->phone;
    }
}
