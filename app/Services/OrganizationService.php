<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Interface\OrganizationServiceInterface;
use App\Services\Interface\ScheduleServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrganizationService implements OrganizationServiceInterface
{
    public function __construct(
        protected ScheduleServiceInterface $schedules,
        protected JwtTokenService $jwt,
    ) {}

    public function listForUser(User $user): Collection
    {
        return $user->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $org) => $org->toPublicArray());
    }

    public function getForUser(User $user, string $organizationId): array
    {
        $this->requireMembership($user, $organizationId);
        $org = Organization::query()->find($organizationId);
        if (! $org) {
            throw new NotFoundHttpException('Not found');
        }

        return $org->toPublicArray();
    }

    public function createOrganization(User $user, array $input): array
    {
        return DB::transaction(function () use ($user, $input) {
            $name = trim((string) $input['name']);
            $timezone = (string) ($input['timezone'] ?? $user->timezone ?? 'UTC');
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'timezone' => $timezone,
                'locale' => $input['locale'] ?? 'en',
                'currency' => $input['currency'] ?? 'USD',
                'brand_color' => $input['brandColor'] ?? $input['brand_color'] ?? null,
                'platform_plan' => 'FREE',
                'settings' => [],
            ]);

            $user->organizations()->attach($organization->id, [
                'type' => OrganizationRole::Owner->value,
                'status' => MembershipStatus::Active->value,
            ]);

            $this->schedules->createDefaultSchedule($user, $organization->id);

            return $organization->toPublicArray();
        });
    }

    public function updateOrganization(User $user, string $organizationId, array $input): array
    {
        $role = $this->requireMembership($user, $organizationId);
        if ($role !== OrganizationRole::Owner) {
            throw new AccessDeniedHttpException('Insufficient role');
        }

        $org = Organization::query()->findOrFail($organizationId);
        foreach (['name', 'timezone', 'locale', 'currency'] as $field) {
            if (array_key_exists($field, $input)) {
                $org->{$field} = $input[$field];
            }
        }
        if (array_key_exists('brandColor', $input)) {
            $org->brand_color = $input['brandColor'];
        }
        if (array_key_exists('logoUrl', $input)) {
            $org->logo_url = $input['logoUrl'];
        }
        if (array_key_exists('settings', $input)) {
            $org->settings = $input['settings'];
        }
        $org->save();

        return $org->toPublicArray();
    }

    public function deleteOrganization(User $user, string $organizationId): void
    {
        $role = $this->requireMembership($user, $organizationId);
        if ($role !== OrganizationRole::Owner) {
            throw new AccessDeniedHttpException('Insufficient role');
        }
        Organization::query()->whereKey($organizationId)->delete();
    }

    /**
     * @return array{accessToken:string,organizationId:string,role:string}
     */
    public function switchOrganization(User $user, string $organizationId): array
    {
        $role = $this->requireMembership($user, $organizationId);

        return [
            'accessToken' => $this->jwt->signAccessToken($user, $organizationId, $role),
            'organizationId' => $organizationId,
            'role' => $role->value,
        ];
    }

    public function listMembers(User $user, string $organizationId): array
    {
        $this->requireMembership($user, $organizationId);
        $org = Organization::query()->with('users')->findOrFail($organizationId);

        return $org->users->map(fn (User $member) => [
            'id' => $member->membership->organization_id.'-'.$member->id,
            'organizationId' => $organizationId,
            'userId' => $member->id,
            'phone' => $member->phone,
            'role' => $member->membership->type,
            'status' => $member->membership->status,
            'createdAt' => optional($member->membership->created_at)?->toIso8601String(),
        ])->values()->all();
    }

    private function requireMembership(User $user, string $organizationId): OrganizationRole
    {
        $membership = $user->organizations()
            ->where('organizations.id', $organizationId)
            ->wherePivot('status', MembershipStatus::Active->value)
            ->first();

        if (! $membership) {
            throw new AccessDeniedHttpException('Not a member of this organization');
        }

        return OrganizationRole::tryFrom((string) $membership->membership->type) ?? OrganizationRole::Member;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;
        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
