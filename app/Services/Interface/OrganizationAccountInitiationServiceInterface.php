<?php

namespace App\Services\Interface;

use App\DTOs\UserProfileDTO;

interface OrganizationAccountInitiationServiceInterface
{
    public function init(string $username, string $name, string $confirmedPassword, string $organizationName): UserProfileDTO;
}
