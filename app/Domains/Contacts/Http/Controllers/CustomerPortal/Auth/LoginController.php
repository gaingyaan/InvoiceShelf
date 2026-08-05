<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth;

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Http\Requests\CustomerPortal\CustomerLoginRequest;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Http\Controller;
use Hash;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(CustomerLoginRequest $request, Company $company)
    {
        $user = Customer::whereRaw('LOWER(email) = ?', [strtolower($request->email)])
            ->where('company_id', $company->id)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->enable_portal) {
            throw ValidationException::withMessages([
                'email' => ['Customer portal not available for this user.'],
            ]);
        }

        Auth::guard('customer')->login($user);

        return response()->json([
            'success' => true,
        ]);
    }
}
