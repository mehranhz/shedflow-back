<?php

namespace App\Services;

use App\Models\User;
use App\Services\Interface\ProfileServiceInterface;

class ProfileService implements ProfileServiceInterface
{
    public function __construct(protected JwtTokenService $jwt) {}

    /**
     * @return array<string, mixed>
     */
    public function getProfile(User $user, ?string $activeOrgId = null, ?string $impersonatingOrgId = null): array
    {
        $memberships = $user->organizations()
            ->wherePivot('status', 'ACTIVE')
            ->get()
            ->map(function ($organization) use ($user) {
                return array_merge($organization->toPublicArray(), [
                    'membership' => [
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                        'type' => $organization->membership->type,
                        'status' => $organization->membership->status,
                    ],
                ]);
            })
            ->values()
            ->all();

        $active = null;
        if ($impersonatingOrgId) {
            $active = collect($memberships)->firstWhere('id', $impersonatingOrgId)
                ?? ($user->organizations()->where('organizations.id', $impersonatingOrgId)->first()?->toPublicArray());
        } elseif ($activeOrgId) {
            $active = collect($memberships)->firstWhere('id', $activeOrgId);
        }
        $active ??= $memberships[0] ?? null;

        return array_merge($this->jwt->publicUser($user), [
            'phoneVerifiedAt' => optional($user->phone_verified_at)?->toIso8601String(),
            'memberships' => $memberships,
            'activeOrganization' => $active,
            'impersonatingOrgId' => $impersonatingOrgId,
        ]);
    }
}
