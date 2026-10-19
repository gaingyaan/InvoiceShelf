<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EstimatePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        if (Auth::guard('customer')->check()) {
            abort_unless($estimate->customer_id === Auth::guard('customer')->id(), 403);
        } elseif (Auth::guard('web')->check() || Auth::guard('sanctum')->check()) {
            abort_unless(Auth::user()->hasCompany($estimate->company_id), 403);
        }

        if ($request->has('preview')) {
            return $estimate->getPDFData();
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }
}
