<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Services\CustomerSessionService;
use Illuminate\Http\Request;

class CustomerSessionController extends APIController
{
    public function __construct(protected CustomerSessionService $sessions) {}

    public function me(Request $request)
    {
        return $this->success(data: $this->sessions->profile($request->user(), $request));
    }

    public function switchBusiness(Request $request, string $organizationId)
    {
        return $this->success(
            data: $this->sessions->switchBusiness($request->user(), $organizationId),
        );
    }
}
