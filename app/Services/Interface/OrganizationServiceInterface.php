<?php

namespace App\Services\Interface;

use App\Models\User;
use Illuminate\Support\Collection;

interface OrganizationServiceInterface
{
    public function listForUser(User $user): Collection;

    public function getForUser(User $user, string $organizationId): array;

    public function createOrganization(User $user, array $input): array;

    public function updateOrganization(User $user, string $organizationId, array $input): array;

    public function deleteOrganization(User $user, string $organizationId): void;

    /**
     * @return array{accessToken:string,organizationId:string,role:string}
     */
    public function switchOrganization(User $user, string $organizationId): array;

    public function listMembers(User $user, string $organizationId): array;
}
