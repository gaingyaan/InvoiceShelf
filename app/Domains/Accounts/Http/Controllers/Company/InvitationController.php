<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Http\Resources\CompanyInvitationResource;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = Company::find($request->header('company'));

        $invitations = CompanyInvitation::where('company_id', $company->id)
            ->pending()
            ->with(['role', 'invitedBy'])
            ->latest()
            ->get();

        return response()->json([
            'invitations' => CompanyInvitationResource::collection($invitations),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role_id' => 'required|exists:roles,id',
        ]);

        $company = Company::find($request->header('company'));

        $invitation = $this->invitationService->invite(
            $company,
            $request->email,
            $request->role_id,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'invitation' => new CompanyInvitationResource($invitation->load(['company', 'role', 'invitedBy'])),
        ]);
    }

    public function destroy(CompanyInvitation $companyInvitation): JsonResponse
    {
        if ($companyInvitation->status !== CompanyInvitation::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending invitations can be cancelled.',
            ], 422);
        }

        $companyInvitation->delete();

        return response()->json(['success' => true]);
    }
}
