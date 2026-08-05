<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Http\Requests\LoginRequest;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->username)])->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Auto-accept invitation if token is provided
        if ($request->has('invitation_token') && $request->invitation_token) {
            $invitation = CompanyInvitation::where('token', $request->invitation_token)
                ->pending()
                ->first();

            if ($invitation) {
                app(InvitationService::class)->accept($invitation, $user);
            }
        }

        return response()->json([
            'type' => 'Bearer',
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function check()
    {
        return Auth::check();
    }
}
