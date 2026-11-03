<?php

namespace App\Services;

use App\DTOs\UserProfileDTO;
use App\Services\Interface\AuthenticationServiceInterface;
use App\Services\Interface\OrganizationAccountInitiationServiceInterface;
use App\Services\Interface\OrganizationServiceInterface;
use App\Services\Interface\ScheduleServiceInterface;

class OrganizationAccountInitiationService implements OrganizationAccountInitiationServiceInterface
{
    public function __construct(
        protected AuthenticationServiceInterface $authenticationService,
        protected OrganizationServiceInterface $organizationService,
        protected ScheduleServiceInterface $scheduleService) {}

    public function init(string $username, string $name, string $confirmedPassword, string $organizationName): UserProfileDTO
    {
        $user = $this->authenticationService->register($username, $name, $confirmedPassword);
        $this->organizationService->createOrganization();
        // TODO: Implement init() method.
    }
}
