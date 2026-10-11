<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends APIController
{
    public function __construct(protected ProfileService $profileService)
    {
    }

    public function getProfile(Request $request)
    {

        $user = $request->user("sanctum");

        return $this->success(data: $this->profileService->getProfile($user)->toArray());
    }
}
