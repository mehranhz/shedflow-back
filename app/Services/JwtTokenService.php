<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Customer;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Support\Str;

class JwtTokenService
{
    /**
     * @return array{accessToken:string,refreshToken:string,user:array<string,mixed>}
     */
    public function issueSession(
        User $user,
        ?string $organizationId = null,
        ?OrganizationRole $role = null,
        array $meta = [],
        ?ActorType $forceActorType = null,
    ): array {
        $actorType = $forceActorType ?? $this->resolveActorType($user);
        $customerOrgId = null;
        $customerId = null;

        if ($actorType === ActorType::Staff) {
            if (! $organizationId || ! $role) {
                [$organizationId, $role] = $this->pickActiveMembership($user, $organizationId);
            }
        } else {
            $organizationId = null;
            $role = null;
            $preferredOrgId = isset($meta['customerOrganizationId']) && is_string($meta['customerOrganizationId'])
                ? $meta['customerOrganizationId']
                : null;
            [$customerOrgId, $customerId] = $this->pickActiveCustomerOrg($user, $preferredOrgId);
        }

        $refresh = $this->createRefreshToken($user, $meta);

        return [
            'accessToken' => $this->signAccessToken(
                $user,
                $organizationId,
                $role,
                null,
                $actorType,
                $customerOrgId,
                $customerId,
            ),
            'refreshToken' => $refresh['raw'],
            'user' => $this->publicUser($user),
            'actorType' => $actorType->value,
        ];
    }

    public function signAccessToken(
        User $user,
        ?string $organizationId = null,
        ?OrganizationRole $role = null,
        ?string $impersonatingOrgId = null,
        ?ActorType $actorType = null,
        ?string $customerOrgId = null,
        ?string $customerId = null,
    ): string {
        $actorType ??= $organizationId && $role
            ? ActorType::Staff
            : $this->resolveActorType($user);

        if ($actorType === ActorType::Staff && (! $organizationId || ! $role)) {
            [$organizationId, $role] = $this->pickActiveMembership($user, $organizationId);
        }

        if ($actorType === ActorType::Customer && ! $customerOrgId) {
            [$customerOrgId, $customerId] = $this->pickActiveCustomerOrg($user, $customerOrgId);
        }

        $claims = [
            'sub' => $user->id,
            'phone' => $user->phone,
            'typ' => 'access',
            'actorType' => $actorType->value,
            'jti' => (string) Str::uuid(),
        ];

        if ($actorType === ActorType::Staff && $organizationId && $role) {
            $claims['orgId'] = $organizationId;
            $claims['role'] = $role->value;
        }

        if ($actorType === ActorType::Customer && $customerOrgId) {
            $claims['customerOrgId'] = $customerOrgId;
        }

        if ($actorType === ActorType::Customer && $customerId) {
            $claims['customerId'] = $customerId;
        }

        if ($impersonatingOrgId) {
            $claims['impersonatingOrgId'] = $impersonatingOrgId;
        }

        return Jwt::encode($claims, ttlSeconds: (int) config('jwt.ttl', 900));
    }

    /**
     * @return array{accessToken:string,refreshToken:string}
     */
    public function rotateRefreshToken(string $rawToken, ?string $organizationId = null, array $meta = []): array
    {
        $existing = RefreshToken::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $existing || $existing->revoked_at !== null || $existing->expires_at->isPast()) {
            if ($existing?->revoked_at) {
                RefreshToken::query()
                    ->where('user_id', $existing->user_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
            abort(401, 'Invalid refresh token');
        }

        $user = User::query()->findOrFail($existing->user_id);
        $next = $this->createRefreshToken($user, $meta);
        $existing->update([
            'revoked_at' => now(),
            'replaced_by_id' => $next['id'],
        ]);

        $actorType = $this->resolveActorType($user);

        if ($actorType === ActorType::Staff) {
            [$orgId, $role] = $this->pickActiveMembership($user, $organizationId);

            return [
                'accessToken' => $this->signAccessToken($user, $orgId, $role, null, ActorType::Staff),
                'refreshToken' => $next['raw'],
            ];
        }

        [$customerOrgId, $customerId] = $this->pickActiveCustomerOrg($user, $organizationId);

        return [
            'accessToken' => $this->signAccessToken(
                $user,
                null,
                null,
                null,
                ActorType::Customer,
                $customerOrgId,
                $customerId,
            ),
            'refreshToken' => $next['raw'],
        ];
    }

    public function resolveActorType(User $user): ActorType
    {
        $hasMembership = $user->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->exists();

        return $hasMembership ? ActorType::Staff : ActorType::Customer;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    public function pickActiveCustomerOrg(User $user, ?string $preferredOrgId = null): array
    {
        if ($preferredOrgId) {
            $preferred = Customer::query()
                ->where('user_id', $user->id)
                ->where('organization_id', $preferredOrgId)
                ->first();
            if ($preferred) {
                return [$preferred->organization_id, $preferred->id];
            }
        }

        $first = Customer::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();

        if (! $first) {
            return [null, null];
        }

        return [$first->organization_id, $first->id];
    }

    public function revokeRefreshToken(?string $rawToken, string $userId): void
    {
        if (! $rawToken) {
            RefreshToken::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return;
        }

        RefreshToken::query()
            ->where('user_id', $userId)
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array{0:?string,1:?OrganizationRole}
     */
    public function pickActiveMembership(User $user, ?string $preferredOrgId = null): array
    {
        $memberships = $user->organizations()
            ->wherePivot('status', 'ACTIVE')
            ->get();

        if ($memberships->isEmpty()) {
            return [null, null];
        }

        if ($preferredOrgId) {
            $preferred = $memberships->firstWhere('id', $preferredOrgId);
            if ($preferred) {
                return [
                    $preferred->id,
                    OrganizationRole::tryFrom((string) $preferred->membership->type) ?? OrganizationRole::Member,
                ];
            }
        }

        $first = $memberships->first();

        return [
            $first->id,
            OrganizationRole::tryFrom((string) $first->membership->type) ?? OrganizationRole::Member,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'phone' => $user->phone,
            'email' => $user->email,
            'name' => $user->name,
            'timezone' => $user->timezone ?? 'UTC',
            'locale' => $user->locale ?? 'en',
            'createdAt' => optional($user->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array{raw:string,id:string}
     */
    private function createRefreshToken(User $user, array $meta = []): array
    {
        $raw = Str::random(64);
        $token = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addSeconds((int) config('jwt.refresh_ttl', 60 * 60 * 24 * 30)),
            'user_agent' => $meta['userAgent'] ?? null,
            'ip' => $meta['ip'] ?? null,
            'created_at' => now(),
        ]);

        return ['raw' => $raw, 'id' => $token->id];
    }
}
