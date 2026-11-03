<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Services\Interface\OrganizationServiceInterface;
use Illuminate\Http\Request;

class OrganizationController extends APIController
{
    public function __construct(protected OrganizationServiceInterface $organizationService) {}

    public function index(Request $request)
    {
        return response()->json($this->organizationService->listForUser($request->user())->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
            'currency' => ['nullable', 'string', 'size:3'],
            'brandColor' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json(
            $this->organizationService->createOrganization($request->user(), $data),
            201,
        );
    }

    public function show(Request $request, string $organization)
    {
        return response()->json($this->organizationService->getForUser($request->user(), $organization));
    }

    public function update(Request $request, string $organization)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'locale' => ['sometimes', 'string', 'max:16'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'brandColor' => ['nullable', 'string', 'max:32'],
            'logoUrl' => ['nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'array'],
        ]);

        return response()->json(
            $this->organizationService->updateOrganization($request->user(), $organization, $data),
        );
    }

    public function destroy(Request $request, string $organization)
    {
        $this->organizationService->deleteOrganization($request->user(), $organization);

        return response()->noContent();
    }

    public function switchOrg(Request $request, string $organization)
    {
        return response()->json(
            $this->organizationService->switchOrganization($request->user(), $organization),
        );
    }

    public function members(Request $request, string $organization)
    {
        return response()->json(
            $this->organizationService->listMembers($request->user(), $organization),
        );
    }
}
