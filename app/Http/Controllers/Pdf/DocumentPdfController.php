<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Document\EstimateService;
use App\Services\Document\InvoiceService;
use App\Services\Document\PaymentService;
use Illuminate\Http\Request;

class DocumentPdfController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly EstimateService $estimateService,
        private readonly PaymentService $paymentService,
    ) {}

    public function invoice(Request $request, Invoice $invoice)
    {
        if ($request->has('preview')) {
            return $this->invoiceService->getPdfData($invoice);
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }

    public function estimate(Request $request, Estimate $estimate)
    {
        if ($request->has('preview')) {
            return $this->estimateService->getPdfData($estimate);
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    public function payment(Request $request, Payment $payment)
    {
        if ($request->has('preview')) {
            // Through the service, so the preview gets the same shared data and
            // the same custom-override resolution as the rendered receipt. This
            // used to name the built-in view directly, so a preview ignored an
            // override and rendered with no data at all.
            return $this->paymentService->getPdfData($payment);
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
