<?php

namespace App\Services\Interface;

use App\Models\User;

interface ProfileServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getProfile(User $user, ?string $activeOrgId = null, ?string $impersonatingOrgId = null): array;
}
