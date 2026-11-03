<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Interface\InvitationServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvitationService implements InvitationServiceInterface
{
    public function list(User $user, string $organizationId): array
    {
        $this->requireStaff($user, $organizationId);

        return Invitation::query()
            ->where('organization_id', $organizationId)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invitation $invitation) => $invitation->toPublicArray())
            ->all();
    }

    public function create(User $user, string $organizationId, string $phone, string $role): array
    {
        $this->requireStaff($user, $organizationId);
        if (! in_array($role, [OrganizationRole::Admin->value, OrganizationRole::Member->value], true)) {
            throw new AccessDeniedHttpException('Invalid invitation role');
        }

        $raw = Str::random(48);
        $invitation = Invitation::query()->create([
            'organization_id' => $organizationId,
            'phone' => trim($phone),
            'role' => $role,
            'token_hash' => hash('sha256', $raw),
            'invited_by_id' => $user->id,
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
        ]);

        return $invitation->toPublicArray($raw);
    }

    public function revoke(User $user, string $organizationId, string $invitationId): void
    {
        $this->requireStaff($user, $organizationId);
        $deleted = Invitation::query()
            ->where('organization_id', $organizationId)
            ->whereKey($invitationId)
            ->whereNull('accepted_at')
            ->delete();
        if (! $deleted) {
            throw new NotFoundHttpException('Not found');
        }
    }

    public function accept(User $user, string $rawToken): array
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();
        if (! $invitation || $invitation->accepted_at || $invitation->expires_at->isPast()) {
            throw new NotFoundHttpException('Invitation not found or expired');
        }
        if (trim((string) $user->phone) !== trim((string) $invitation->phone)) {
            throw new AccessDeniedHttpException('Invitation phone mismatch');
        }

        return DB::transaction(function () use ($user, $invitation) {
            $exists = $user->organizations()->where('organizations.id', $invitation->organization_id)->exists();
            if ($exists) {
                throw new ConflictHttpException('Already a member');
            }
            $user->organizations()->attach($invitation->organization_id, [
                'type' => $invitation->role,
                'status' => MembershipStatus::Active->value,
            ]);
            $invitation->accepted_at = now();
            $invitation->save();

            return [
                'organizationId' => $invitation->organization_id,
                'role' => $invitation->role,
            ];
        });
    }

    private function requireStaff(User $user, string $organizationId): void
    {
        $membership = $user->organizations()
            ->where('organizations.id', $organizationId)
            ->wherePivot('status', MembershipStatus::Active->value)
            ->first();
        if (! $membership) {
            throw new AccessDeniedHttpException('Not a member of this organization');
        }
        $role = OrganizationRole::tryFrom((string) $membership->membership->type) ?? OrganizationRole::Member;
        if (! $role->isStaff()) {
            throw new AccessDeniedHttpException('Insufficient role');
        }
    }
}
