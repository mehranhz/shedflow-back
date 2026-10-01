<?php

namespace App\Services;

use App\DTOs\InvitationDTO;
use App\Events\JoinToOrganizationInvitationCreated;
use App\Models\Invitation;
use App\Services\Interface\InvitationServiceInterface;

class InvitationService implements InvitationServiceInterface
{
    public function store(InvitationDTO $invitationDTO): Invitation
    {
        $invitation =  Invitation::create([
            "user_id"=>$invitationDTO->user->id,
            "organization_id"=>$invitationDTO->organization->id,
            "phone"=>$invitationDTO->phone,
            "role"=>$invitationDTO->role,
        ]);

        JoinToOrganizationInvitationCreated::dispatch($invitation);

        return $invitation;
    }
}
