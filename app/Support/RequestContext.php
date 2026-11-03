<?php

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Http\Request;

final class RequestContext
{
    public function __construct(
        public readonly User $user,
        public readonly ActorType $actorType,
        public readonly ?string $organizationId,
        public readonly ?OrganizationRole $role,
        public readonly ?string $customerOrganizationId,
        public readonly ?string $customerId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var User $user */
        $user = $request->user();

        $actorType = $request->attributes->get('jwt_actor_type');
        if (! is_string($actorType)) {
            $actorType = ActorType::Customer->value;
        }

        return new self(
            user: $user,
            actorType: ActorType::tryFrom($actorType) ?? ActorType::Customer,
            organizationId: $request->attributes->get('jwt_org_id'),
            role: $request->attributes->get('jwt_role'),
            customerOrganizationId: $request->attributes->get('jwt_customer_org_id'),
            customerId: $request->attributes->get('jwt_customer_id'),
        );
    }

    public function isStaff(): bool
    {
        return $this->actorType === ActorType::Staff;
    }

    public function isCustomer(): bool
    {
        return $this->actorType === ActorType::Customer;
    }
}
