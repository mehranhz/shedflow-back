<?php

namespace App\Http\Controllers;

use App\DTOs\InvitationDTO;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Models\Invitation;
use App\Models\Organization;
use App\Services\Interface\InvitationServiceInterface;

class InvitationController extends Controller
{
    public function __construct(protected InvitationServiceInterface $invitationService)
    {

    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvitationRequest $request)
    {
        $user = $request->user("sanctum");

        $invitation = $this->invitationService->store(InvitationDTO::fromStoreInvitationRequest($user,$request));
    }

    /**
     * Display the specified resource.
     */
    public function show(Invitation $invitation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvitationRequest $request, Invitation $invitation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invitation $invitation)
    {
        //
    }
}
