<?php

namespace App\Http\Controllers\v1;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerController extends Controller
{
    public function index(Request $request, string $organization)
    {
        $this->requireMembership($request->user(), $organization);

        $items = Customer::query()
            ->where('organization_id', $organization)
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'organizationId' => $customer->organization_id,
                'email' => $customer->email,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'timezone' => $customer->timezone,
                'notes' => $customer->notes,
                'createdAt' => optional($customer->created_at)?->toIso8601String(),
                'updatedAt' => optional($customer->updated_at)?->toIso8601String(),
            ]);

        return response()->json([
            'items' => $items,
            'total' => $items->count(),
            'page' => 1,
            'limit' => max($items->count(), 25),
            'pageCount' => 1,
        ]);
    }

    public function show(Request $request, string $organization, string $customer)
    {
        $this->requireMembership($request->user(), $organization);
        $model = Customer::query()
            ->where('organization_id', $organization)
            ->whereKey($customer)
            ->first();
        if (! $model) {
            throw new NotFoundHttpException('Not found');
        }

        return response()->json([
            'id' => $model->id,
            'organizationId' => $model->organization_id,
            'email' => $model->email,
            'name' => $model->name,
            'phone' => $model->phone,
            'timezone' => $model->timezone,
            'notes' => $model->notes,
            'createdAt' => optional($model->created_at)?->toIso8601String(),
            'updatedAt' => optional($model->updated_at)?->toIso8601String(),
        ]);
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
}
