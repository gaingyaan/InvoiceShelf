<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\RecurringInvoice;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

class RecurringInvoiceFrequencyController extends Controller
{
    public function __invoke(Request $request)
    {
        $nextInvoiceAt = RecurringInvoice::getNextInvoiceDate($request->frequency, $request->starts_at);

        return response()->json([
            'success' => true,
            'next_invoice_at' => $nextInvoiceAt,
        ]);
    }
}
