<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Services\Interface\InvitationServiceInterface;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(protected InvitationServiceInterface $invitationService) {}

    public function index(Request $request, string $organization)
    {
        return response()->json($this->invitationService->list($request->user(), $organization));
    }

    public function store(Request $request, string $organization)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'role' => ['required', 'in:ADMIN,MEMBER'],
        ]);

        return response()->json(
            $this->invitationService->create($request->user(), $organization, $data['phone'], $data['role']),
            201,
        );
    }

    public function destroy(Request $request, string $organization, string $invitation)
    {
        $this->invitationService->revoke($request->user(), $organization, $invitation);

        return response()->noContent();
    }

    public function accept(Request $request, string $token)
    {
        return response()->json($this->invitationService->accept($request->user(), $token));
    }
}
