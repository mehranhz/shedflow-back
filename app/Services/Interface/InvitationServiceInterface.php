<?php

namespace App\Services\Interface;

use App\DTOs\InvitationDTO;
use App\Models\Invitation;

interface InvitationServiceInterface
{
    public function store(InvitationDTO $invitationDTO): Invitation;
}
