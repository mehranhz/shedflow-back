<?php

namespace App\Http\Controllers;

use App\DTOs\OrganizationDTO;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\Interface\OrganizationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{

    public function __construct(protected OrganizationServiceInterface $organizationService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum');

        return response()->json([
            "organizations" => $this->organizationService->getUserOrganizations($user),
        ],200);
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
