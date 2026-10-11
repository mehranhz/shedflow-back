<?php

namespace App\DTOs;

use Illuminate\Notifications\Notifiable;

class ContactDTO
{
    use Notifiable;

    public function __construct(public string $phone)
    {

    }
}
