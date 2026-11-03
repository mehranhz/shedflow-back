<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerSessionService
{
    public function __construct(
        protected JwtTokenService $jwt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profile(User $user, Request $request): array
    {
        return [
            'user' => $this->jwt->publicUser($user),
            'businesses' => $this->businessesForUser($user),
            'activeCustomerOrgId' => $request->attributes->get('jwt_customer_org_id'),
            'activeCustomerId' => $request->attributes->get('jwt_customer_id'),
        ];
    }

    /**
     * @return array{accessToken:string,customerOrgId:string,customerId:string}
     */
    public function switchBusiness(User $user, string $organizationId): array
    {
        $customer = Customer::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $customer) {
            throw new NotFoundHttpException('Customer link not found');
        }

        return [
            'accessToken' => $this->jwt->signAccessToken(
                $user,
                null,
                null,
                null,
                ActorType::Customer,
                $customer->organization_id,
                $customer->id,
            ),
            'customerOrgId' => $customer->organization_id,
            'customerId' => $customer->id,
        ];
    }

    /**
     * @return list<array{
     *   organizationId:string,
     *   slug:string,
     *   name:string,
     *   brandColor:?string,
     *   logoUrl:?string,
     *   customerId:string,
     *   lastBookingAt:?string
     * }>
     */
    public function businessesForUser(User $user): array
    {
        return Customer::query()
            ->where('user_id', $user->id)
            ->with('organization:id,slug,name,brand_color,logo_url')
            ->withMax('bookings', 'start_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Customer $customer) => [
                'organizationId' => $customer->organization_id,
                'slug' => (string) $customer->organization?->slug,
                'name' => (string) $customer->organization?->name,
                'brandColor' => $customer->organization?->brand_color,
                'logoUrl' => $customer->organization?->logo_url,
                'customerId' => $customer->id,
                'lastBookingAt' => $customer->bookings_max_start_at
                    ? CarbonImmutable::parse($customer->bookings_max_start_at)->toIso8601String()
                    : null,
            ])
            ->values()
            ->all();
    }
}
