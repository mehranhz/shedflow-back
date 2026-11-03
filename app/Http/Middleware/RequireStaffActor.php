<?php

namespace App\Http\Middleware;

use App\Enums\ActorType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorType = $request->attributes->get('jwt_actor_type');
        if ($actorType === ActorType::Customer->value) {
            abort(403, 'Staff access required');
        }

        return $next($request);
    }
}
