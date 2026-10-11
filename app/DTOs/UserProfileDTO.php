<?php

namespace App\DTOs;

use App\Models\Organization;
use App\Traits\ToArray;

class UserProfileDTO
{
    use ToArray;
    /**
    * @param Organization[] $memberships
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $phone,
        public string $createdAt,
        public string $phoneVerifiedAt,
        public array $memberships,
        public Organization $activeOrganization,
    )
    {

    }
}
