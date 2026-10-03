<?php

namespace App\Services\Interface;



use App\DTOs\OrganizationDTO;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrganizationServiceInterface
{
    /**
    * @return LengthAwarePaginator<int, Organization>
     */
    public function getUserOrganizations(User $user ): object;

    public function createOrganization(OrganizationDTO $dto): Organization;
}
