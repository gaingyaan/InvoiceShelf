<?php

namespace App\Http\Controllers\Company\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendCustomerStatementRequest;
use App\Mail\SendCustomerStatementMail;
use App\Models\Customer;
use App\Services\CustomerStatementPdfService;
use App\Services\CustomerStatementService;
use App\Services\Mail\CompanyMailConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class SendCustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementService $customerStatementService,
        private readonly CustomerStatementPdfService $customerStatementPdfService,
    ) {}

    public function __invoke(SendCustomerStatementRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('view report', $customer->company);

        $type = $request->validated('type');

        $statement = $this->customerStatementService->statement(
            $customer,
            $type,
            Carbon::createFromFormat('Y-m-d', $request->validated('from_date')),
            Carbon::createFromFormat('Y-m-d', $request->validated($type === CustomerStatementService::TYPE_OUTSTANDING ? 'as_of' : 'to_date')),
            PHP_INT_MAX,
        );
        $pdf = $this->customerStatementPdfService->render($statement);

        CompanyMailConfigService::apply($customer->company_id);

        $mail = Mail::to($request->validated('to'));
        if ($request->filled('cc')) {
            $mail->cc($request->validated('cc'));
        }
        if ($request->filled('bcc')) {
            $mail->bcc($request->validated('bcc'));
        }

        $mail->send(new SendCustomerStatementMail([
            ...$request->safe()->only(['to', 'cc', 'bcc', 'subject', 'body']),
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'customer' => $customer,
            'pdf' => $pdf,
            'filename' => __('Customer Statement').' '.$customer->name.'.pdf',
        ]));

        return response()->json(['success' => true]);
    }
}
