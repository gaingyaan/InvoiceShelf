<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Http\Requests\DeleteEstimatesRequest;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Http\Requests\SendEstimatesRequest;
use App\Domains\Sales\Http\Resources\EstimateResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use App\Domains\Sales\Jobs\GenerateEstimatePdfJob;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class EstimatesController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $estimates = Estimate::whereCompany()
            ->join('customers', 'customers.id', '=', 'estimates.customer_id')
            ->applyFilters($request->all())
            ->select('estimates.*', 'customers.name')
            ->latest()
            ->paginateData($limit);

        return EstimateResource::collection($estimates)
            ->additional(['meta' => [
                'estimate_total_count' => Estimate::whereCompany()->count(),
            ]]);
    }

    public function store(EstimatesRequest $request)
    {
        $this->authorize('create', Estimate::class);

        $estimate = $this->estimateService->create(
            attributes: $request->getEstimatePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        if ($request->has('estimateSend')) {
            $this->estimateService->send($estimate, $request->only(['title', 'body']));
        }

        GenerateEstimatePdfJob::dispatch($estimate);

        return new EstimateResource($estimate);
    }

    public function show(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);

        return new EstimateResource($estimate);
    }

    public function update(EstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('update', $estimate);

        $estimate = $this->estimateService->update(
            estimate: $estimate,
            attributes: $request->getEstimatePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return new EstimateResource($estimate);
    }

    public function delete(DeleteEstimatesRequest $request)
    {
        $this->authorize('delete multiple estimates');

        $ids = Estimate::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        Estimate::destroy($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    public function send(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $response = $this->estimateService->send($estimate, $request->all());

        return response()->json($response);
    }

    public function sendPreview(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->estimateService->sendEstimateData($estimate, $request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        return $markdown->render('emails.send.estimate', ['data' => $data]);
    }

    public function clone(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);
        $this->authorize('create', Estimate::class);

        $newEstimate = $this->estimateService->clone($estimate);

        return new EstimateResource($newEstimate);
    }

    public function convertToInvoice(Request $request, Estimate $estimate)
    {
        // Authorize access to the source estimate (tenant isolation) in addition
        // to the ability to create an invoice.
        $this->authorize('view', $estimate);
        $this->authorize('create', Invoice::class);

        $invoice = $this->estimateService->convertToInvoice($estimate);

        return new InvoiceResource($invoice);
    }

    public function changeStatus(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $this->estimateService->changeStatus($estimate, $request->status);

        return response()->json([
            'success' => true,
        ]);
    }

    private function customFields(EstimatesRequest $request): ?iterable
    {
        $customFields = $request->input('customFields');

        return is_iterable($customFields) ? $customFields : null;
    }
}
