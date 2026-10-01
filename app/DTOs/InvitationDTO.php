<?php

namespace App\DTOs;

use App\Enums\OrganizationRole;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Organization;
use App\Models\User;

class InvitationDTO
{
    public function __construct(
        public readonly User $user,
        public readonly Organization $organization,
        public readonly string $phone ,
        public readonly string $role
    )
    {

    }

    public static function fromStoreInvitationRequest(User $user,StoreInvitationRequest $storeInvitationRequest): self
    {
        $organization = Organization::findOrFail($storeInvitationRequest->organization);
        return new self($user, $organization, $storeInvitationRequest->phone, $storeInvitationRequest->role ??OrganizationRole::Member->value);
    }
}
