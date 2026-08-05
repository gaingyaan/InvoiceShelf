<?php

namespace App\Domains\Receivables\Http\Controllers\Company;

use App\Domains\Receivables\Application\PaymentAllocationService;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Http\Requests\DeletePaymentsRequest;
use App\Domains\Receivables\Http\Requests\PaymentRequest;
use App\Domains\Receivables\Http\Requests\ReplacePaymentAllocationsRequest;
use App\Domains\Receivables\Http\Requests\SendPaymentRequest;
use App\Domains\Receivables\Http\Resources\PaymentResource;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;

class PaymentsController extends Controller
{
    public function __construct(
        private readonly PaymentAllocationService $paymentAllocationService,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $payments = Payment::with(['allocations.invoice'])
            ->whereCompany()
            ->join('customers', 'customers.id', '=', 'payments.customer_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->applyFilters($request->all())
            ->select('payments.*', 'customers.name', 'payment_methods.name as payment_mode')
            ->latest()
            ->paginateData($limit);

        return PaymentResource::collection($payments)
            ->additional(['meta' => [
                'payment_total_count' => Payment::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(PaymentRequest $request)
    {
        $this->authorize('create', Payment::class);

        $payment = $this->paymentService->create(
            attributes: $request->getPaymentPayload(),
            allocations: $request->validated('allocations') ?? [],
            customFields: $this->customFields($request),
        );

        return new PaymentResource($payment);
    }

    public function show(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment->load(['allocations.invoice']));
    }

    public function update(PaymentRequest $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        $payment = $this->paymentService->update(
            payment: $payment,
            attributes: $request->getPaymentPayload(),
            replaceAllocations: $request->exists('allocations'),
            allocations: $request->validated('allocations') ?? [],
            customFields: $this->customFields($request),
        );

        return new PaymentResource($payment);
    }

    public function replaceAllocations(ReplacePaymentAllocationsRequest $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        abort_unless((int) $payment->company_id === (int) $request->header('company'), 404);

        $payment = $this->paymentAllocationService->replace($payment, $request->validated('allocations'));

        return new PaymentResource($payment->load(['allocations.invoice']));
    }

    public function delete(DeletePaymentsRequest $request)
    {
        $this->authorize('delete multiple payments');

        $ids = Payment::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->paymentService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    public function send(SendPaymentRequest $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $response = $this->paymentService->send($payment, $request->all());

        return response()->json($response);
    }

    public function sendPreview(Request $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->paymentService->sendPaymentData($payment, $request->all());
        $data['url'] = $payment->paymentPdfUrl;

        return $markdown->render('emails.send.payment', ['data' => $data]);
    }

    private function customFields(PaymentRequest $request): ?iterable
    {
        $customFields = $request->input('customFields');

        return is_iterable($customFields) ? $customFields : null;
    }
}
