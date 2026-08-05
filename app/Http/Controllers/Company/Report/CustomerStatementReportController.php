<?php

namespace App\Http\Controllers\Company\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerStatementRequest;
use App\Models\Customer;
use App\Services\CustomerStatementPdfService;
use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Silber\Bouncer\BouncerFacade;

class CustomerStatementReportController extends Controller
{
    public function __construct(
        private readonly CustomerStatementService $customerStatementService,
        private readonly CustomerStatementPdfService $customerStatementPdfService,
    ) {}

    public function __invoke(CustomerStatementRequest $request, Customer $customer)
    {
        BouncerFacade::scope()->to($customer->company_id);

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

        if ($request->boolean('preview')) {
            return view('app.pdf.reports.customer-statement', [
                'statement' => $statement,
                'customer' => $customer,
                'company' => $customer->company,
                'currency' => $statement['currency'],
                'logo' => $customer->company->logo_path,
            ]);
        }

        if ($request->boolean('download')) {
            return $pdf->download(__('Customer Statement').' '.$customer->name.'.pdf');
        }

        return $pdf->stream();
    }
}
