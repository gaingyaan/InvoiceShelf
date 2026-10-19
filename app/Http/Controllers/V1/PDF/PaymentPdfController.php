<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentPdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Payment $payment)
    {
        if (Auth::guard('customer')->check()) {
            abort_unless($payment->customer_id === Auth::guard('customer')->id(), 403);
        } elseif (Auth::guard('web')->check() || Auth::guard('sanctum')->check()) {
            abort_unless(Auth::user()->hasCompany($payment->company_id), 403);
        }

        if ($request->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
