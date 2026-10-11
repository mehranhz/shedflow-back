<?php

namespace App\Services;

use App\DTOs\UserProfileDTO;
use App\Models\User;
use App\Services\Interface\ProfileServiceInterface;

class ProfileService implements ProfileServiceInterface
{
    public function getProfile(User $user): UserProfileDTO
    {
        $userOrganizations = $user->organizations->all();

        return new UserProfileDTO(
            id:$user->id,
            name: $user->name,
            phone: $user->phone,
            createdAt: $user->created_at->toDateTimeString(),
            phoneVerifiedAt: $user->phone_verified_at->toDateTimeString(),
            memberships:  $userOrganizations,
            activeOrganization: $userOrganizations[0],
        );
    }
}


