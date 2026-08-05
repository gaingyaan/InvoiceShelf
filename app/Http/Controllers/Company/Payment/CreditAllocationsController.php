<?php

namespace App\Http\Controllers\Company\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreditAllocationRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Document\PaymentAllocationService;

class CreditAllocationsController extends Controller
{
    public function __construct(
        private readonly PaymentAllocationService $paymentAllocationService,
    ) {}

    public function store(CreditAllocationRequest $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        abort_unless((int) $customer->company_id === (int) $request->header('company'), 404);

        $payments = Payment::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->whereIn('id', collect($request->validated('allocations'))->pluck('payment_id')->unique())
            ->get();

        foreach ($payments as $payment) {
            $this->authorize('update', $payment);
        }

        $this->paymentAllocationService->applyCustomerCredits(
            (int) $request->header('company'),
            $customer->id,
            $request->validated('allocations'),
        );

        return response()->json(['success' => true]);
    }
}
