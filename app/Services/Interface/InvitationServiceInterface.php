<?php

namespace App\Services\Interface;

use App\Models\User;

interface InvitationServiceInterface
{
    public function list(User $user, string $organizationId): array;

    public function create(User $user, string $organizationId, string $phone, string $role): array;

    public function revoke(User $user, string $organizationId, string $invitationId): void;

    public function accept(User $user, string $rawToken): array;
}
