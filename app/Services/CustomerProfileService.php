<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerProfileService
{
    /**
     * @return array{
     *   email:string,
     *   name:string,
     *   phone:?string,
     *   timezone:?string,
     *   locale:string,
     *   updatedAt:string,
     *   organizationId:string,
     *   customerId:string
     * }
     */
    public function show(User $user, string $organizationId): array
    {
        $customer = $this->linkedCustomer($user, $organizationId);

        return $this->payload($user, $customer);
    }

    /**
     * @param  array{name?:string,phone?:?string,timezone?:?string,locale?:?string}  $input
     * @return array{
     *   email:string,
     *   name:string,
     *   phone:?string,
     *   timezone:?string,
     *   locale:string,
     *   updatedAt:string,
     *   organizationId:string,
     *   customerId:string
     * }
     */
    public function update(User $user, string $organizationId, array $input, ?string $ifMatch): array
    {
        $customer = $this->linkedCustomer($user, $organizationId);

        if ($ifMatch !== null && $ifMatch !== '') {
            $expected = CarbonImmutable::parse($ifMatch)->utc()->getTimestamp();
            $actual = CarbonImmutable::parse($customer->updated_at)->utc()->getTimestamp();
            if ($expected !== $actual) {
                throw new ConflictHttpException('Profile version conflict');
            }
        }

        return DB::transaction(function () use ($user, $customer, $input) {
            if (array_key_exists('name', $input) && is_string($input['name'])) {
                $customer->name = trim($input['name']);
            }
            if (array_key_exists('phone', $input)) {
                $customer->phone = is_string($input['phone']) ? trim($input['phone']) : null;
            }
            if (array_key_exists('timezone', $input)) {
                $tz = is_string($input['timezone']) ? trim($input['timezone']) : null;
                $customer->timezone = $tz !== '' ? $tz : null;
                $user->timezone = $customer->timezone;
            }
            if (array_key_exists('locale', $input) && is_string($input['locale'])) {
                $user->locale = trim($input['locale']);
            }

            $customer->save();
            $user->save();

            return $this->payload($user->fresh(), $customer->fresh());
        });
    }

    private function linkedCustomer(User $user, string $organizationId): Customer
    {
        $customer = Customer::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $customer) {
            throw new NotFoundHttpException('Not found');
        }

        return $customer;
    }

    /**
     * @return array{
     *   email:string,
     *   name:string,
     *   phone:?string,
     *   timezone:?string,
     *   locale:string,
     *   updatedAt:string,
     *   organizationId:string,
     *   customerId:string
     * }
     */
    private function payload(User $user, Customer $customer): array
    {
        return [
            'email' => (string) ($user->email ?: $customer->email),
            'name' => $customer->name,
            'phone' => $customer->phone,
            'timezone' => $customer->timezone ?? $user->timezone,
            'locale' => (string) ($user->locale ?? 'en'),
            'updatedAt' => $customer->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'organizationId' => $customer->organization_id,
            'customerId' => $customer->id,
        ];
    }
}
