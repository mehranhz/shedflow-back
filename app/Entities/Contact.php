<?php

namespace App\Entities;

use Illuminate\Notifications\Notifiable;

class Contact
{
    use Notifiable;

    public function __construct(public string $phone)
    {

    }
}
