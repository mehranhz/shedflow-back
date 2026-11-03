<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCustomerOrg
{
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->attributes->get('jwt_customer_org_id');
        if (! is_string($orgId) || $orgId === '') {
            abort(400, 'Active customer organization required');
        }

        return $next($request);
    }
}
