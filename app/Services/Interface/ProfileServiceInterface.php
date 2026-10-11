<?php

namespace App\Services\Interface;

use App\DTOs\UserProfileDTO;
use App\Models\User;

interface ProfileServiceInterface
{
    public function getProfile(User $user): UserProfileDTO;
}
