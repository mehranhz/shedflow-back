<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerUserLinkEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerLinkService
{
    public function linkCustomerToUser(Customer $customer, User $user, ?User $actor = null): Customer
    {
        if ($customer->user_id !== null && $customer->user_id !== $user->id) {
            throw new \InvalidArgumentException('Customer is already linked to another user');
        }

        if ($customer->user_id === $user->id) {
            return $customer;
        }

        return DB::transaction(function () use ($customer, $user, $actor) {
            $customer->update(['user_id' => $user->id]);

            CustomerUserLinkEvent::query()->create([
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'organization_id' => $customer->organization_id,
                'action' => 'linked',
                'actor_user_id' => $actor?->id ?? $user->id,
                'created_at' => now(),
            ]);

            return $customer->fresh();
        });
    }

    public function unlinkCustomerFromUser(Customer $customer, ?User $actor = null): Customer
    {
        if ($customer->user_id === null) {
            return $customer;
        }

        return DB::transaction(function () use ($customer, $actor) {
            $previousUserId = $customer->user_id;
            $customer->update(['user_id' => null]);

            CustomerUserLinkEvent::query()->create([
                'user_id' => $previousUserId,
                'customer_id' => $customer->id,
                'organization_id' => $customer->organization_id,
                'action' => 'unlinked',
                'actor_user_id' => $actor?->id,
                'created_at' => now(),
            ]);

            return $customer->fresh();
        });
    }

    /**
     * @return list<Customer>
     */
    public function linkCustomersByEmail(User $user, string $email, ?User $actor = null): array
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return [];
        }

        $customers = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->get();

        $linked = [];
        foreach ($customers as $customer) {
            if ($customer->user_id !== null && $customer->user_id !== $user->id) {
                continue;
            }
            $linked[] = $this->linkCustomerToUser($customer, $user, $actor);
        }

        return $linked;
    }
}
