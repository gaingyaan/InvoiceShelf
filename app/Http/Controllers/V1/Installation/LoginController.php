<?php

namespace App\Http\Controllers\V1\Installation;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => Auth::user(),
            'company' => Auth::user()->companies()->first(),
        ]);
    }
}
