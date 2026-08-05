<?php

namespace App\Domains\Receivables\Http\Controllers;

use App\Domains\Receivables\Http\Resources\PaymentResource;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Http\Request;

class PublicPaymentController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $payment = $emailLog->mailable;
        abort_unless($payment instanceof Payment, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return $payment->getGeneratedPDFOrStream('payment');
    }

    public function getPayment(EmailLog $emailLog)
    {
        $payment = $emailLog->mailable;
        abort_unless($payment instanceof Payment, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return new PaymentResource($payment);
    }
}
