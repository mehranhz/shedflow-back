<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Services\Interface\ProfileServiceInterface;
use Illuminate\Http\Request;

class ProfileController extends APIController
{
    public function __construct(protected ProfileServiceInterface $profileService) {}

    public function getProfile(Request $request)
    {
        $profile = $this->profileService->getProfile(
            $request->user(),
            $request->attributes->get('jwt_org_id'),
            $request->attributes->get('jwt_impersonating_org_id'),
        );

        return $this->success(data: $profile);
    }
}
