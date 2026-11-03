<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Http\Requests\v1\UpdateCustomerProfileRequest;
use App\Services\CustomerProfileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CustomerProfileController extends APIController
{
    public function __construct(protected CustomerProfileService $profiles) {}

    public function show(Request $request)
    {
        $orgId = $this->requireCustomerOrg($request);

        return $this->success(data: $this->profiles->show($request->user(), $orgId));
    }

    public function update(UpdateCustomerProfileRequest $request)
    {
        $orgId = $this->requireCustomerOrg($request);

        try {
            $profile = $this->profiles->update(
                $request->user(),
                $orgId,
                $request->validated(),
                $request->header('If-Match'),
            );
        } catch (ConflictHttpException) {
            return response()->json([
                'error' => [
                    'code' => 'CONFLICT',
                    'message' => 'Profile version conflict',
                ],
            ], 409);
        }

        return $this->success(data: $profile);
    }

    private function requireCustomerOrg(Request $request): string
    {
        $orgId = $request->attributes->get('jwt_customer_org_id');

        if (! is_string($orgId) || $orgId === '') {
            abort(400, 'Active customer organization required');
        }

        return $orgId;
    }
}
