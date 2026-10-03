<?php

namespace App\Http\Controllers\v1;

use App\DTOs\OrganizationDTO;
use App\Http\Requests\v1\StoreOrganizationRequest;
use App\Http\Requests\v1\UpdateOrganizationRequest;
use App\Http\Resources\v1\OrganizationResource;
use App\Models\Organization;
use App\Services\Interface\OrganizationServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use ApiResponse;
    public function __construct(protected OrganizationServiceInterface $organizationService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum');
        return OrganizationResource::collection($this->organizationService->getUserOrganizations($user));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $user = $request->user('sanctum');
        $organization = $this->organizationService
            ->createOrganization(OrganizationDTO::fromStoreOrganizationRequest($user, $request));

        return response()->json([
            "organization" => $organization
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        //
    }
}
