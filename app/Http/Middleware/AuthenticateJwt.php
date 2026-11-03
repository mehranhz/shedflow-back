<?php

namespace App\Http\Middleware;

use App\Enums\ActorType;
use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            abort(401, 'Unauthenticated');
        }

        $token = trim(substr($header, 7));
        try {
            $claims = Jwt::decode($token);
        } catch (\Throwable) {
            abort(401, 'Unauthenticated');
        }

        $userId = $claims['sub'] ?? null;
        if (! is_string($userId)) {
            abort(401, 'Unauthenticated');
        }

        $user = User::query()->find($userId);
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        auth()->setUser($user);

        $actorType = isset($claims['actorType']) && is_string($claims['actorType'])
            ? $claims['actorType']
            : (isset($claims['orgId']) ? ActorType::Staff->value : ActorType::Customer->value);

        $customerOrgHeader = $request->header('X-Customer-Org-Id');
        $customerOrgId = $claims['customerOrgId'] ?? null;
        if ($actorType === ActorType::Customer->value && is_string($customerOrgHeader) && $customerOrgHeader !== '') {
            $customerOrgId = $customerOrgHeader;
        }

        $request->attributes->set('jwt', $claims);
        $request->attributes->set('jwt_actor_type', $actorType);
        $request->attributes->set('jwt_org_id', $actorType === ActorType::Staff->value ? ($claims['orgId'] ?? null) : null);
        $request->attributes->set(
            'jwt_role',
            $actorType === ActorType::Staff->value && isset($claims['role'])
                ? OrganizationRole::tryFrom((string) $claims['role'])
                : null,
        );
        $request->attributes->set('jwt_customer_org_id', $customerOrgId);
        $request->attributes->set('jwt_customer_id', $claims['customerId'] ?? null);
        $request->attributes->set('jwt_impersonating_org_id', $claims['impersonatingOrgId'] ?? null);

        return $next($request);
    }
}
