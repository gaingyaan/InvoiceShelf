<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoicePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Invoice $invoice)
    {
        if (Auth::guard('customer')->check()) {
            abort_unless($invoice->customer_id === Auth::guard('customer')->id(), 403);
        } elseif (Auth::guard('web')->check() || Auth::guard('sanctum')->check()) {
            abort_unless(Auth::user()->hasCompany($invoice->company_id), 403);
        }

        if ($request->has('preview')) {
            return $invoice->getPDFData();
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }
}
