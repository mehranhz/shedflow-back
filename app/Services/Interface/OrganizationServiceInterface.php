<?php

namespace App\Services\Interface;



use App\DTOs\OrganizationDTO;
use App\Models\Organization;
use App\Models\User;

interface OrganizationServiceInterface
{
    /**
     * @param User $user
     * @return OrganizationDTO[]
     */
    public function getUserOrganizations(User $user ): array;

    public function createOrganization(OrganizationDTO $dto): Organization;
}
