<?php

namespace App\Services;

use App\DTOs\OrganizationDTO;
use App\Models\Organization;
use App\Models\User;
use App\Services\Interface\OrganizationServiceInterface;

class OrganizationService implements OrganizationServiceInterface
{
    public function getUserOrganizations(User $user): object
    {

        return $user->organizations()->paginate(2);

    }

    public function createOrganization(OrganizationDTO $dto): Organization
    {
        $organization = Organization::create([
           "name"=>$dto->name,
           "slug"=>$dto->slug,
           "timezone"=>$dto->timezone,
           "locale"=>$dto->locale,
           "currency"=>$dto->currency,
            "brand_color"=>$dto->brandColor,
        ]);

        $dto->user->organizations()->attach($organization, ["type"=>"creator", "status"=>"active"]);

        return $organization;
    }
}
