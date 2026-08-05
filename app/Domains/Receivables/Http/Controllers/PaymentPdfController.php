<?php

namespace App\Domains\Receivables\Http\Controllers;

use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

class PaymentPdfController extends Controller
{
    public function __construct(
        private readonly PaymentPdfDataProvider $paymentPdfDataProvider,
    ) {}

    public function __invoke(Request $request, Payment $payment): mixed
    {
        if ($request->has('preview')) {
            return $this->paymentPdfDataProvider->getPdfData($payment);
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
