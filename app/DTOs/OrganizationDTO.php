<?php

namespace App\DTOs;

use App\Http\Requests\v1\StoreOrganizationRequest;
use App\Models\Organization;
use App\Models\User;

class OrganizationDTO
{
    public function  __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly  User $user,
        public readonly  string $timezone,
        public readonly  string $locale,
        public readonly  string $currency,
        public readonly string $brandColor,

    )
    {

    }
    public static function fromStoreOrganizationRequest(User $user,StoreOrganizationRequest $request): self{


        return new self(
            $request->input('name'),
            $request->input('slug'),
            $user,
            $request->input('timezone'),
            $request->input('locale'),
            $request->input('currency'),
            $request->input('brand_color'),
        );
    }

    public static function fromModel(User $user, Organization $organization)
    {
        return new self(
            $organization->name,
            $organization->slug,
            $user,
            $organization->timezone,
            $organization->locale,
            $organization->currency,
            $organization->brand_color,
        );
    }
}
