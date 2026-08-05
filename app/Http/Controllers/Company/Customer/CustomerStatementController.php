<?php

namespace App\Http\Controllers\Company\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerStatementRequest;
use App\Http\Resources\CustomerStatementResource;
use App\Models\Customer;
use App\Services\CustomerStatementService;
use Carbon\Carbon;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementService $customerStatementService,
    ) {}

    public function __invoke(CustomerStatementRequest $request, Customer $customer): CustomerStatementResource
    {
        $this->authorize('view', $customer);
        $this->authorize('view report', $customer->company);

        $type = $request->validated('type');

        $statement = $this->customerStatementService->statement(
            $customer,
            $type,
            Carbon::createFromFormat('Y-m-d', $request->validated('from_date')),
            Carbon::createFromFormat('Y-m-d', $request->validated($type === CustomerStatementService::TYPE_OUTSTANDING ? 'as_of' : 'to_date')),
            (int) $request->validated('per_page', 50),
            (int) $request->validated('page', 1),
        );

        return new CustomerStatementResource($statement);
    }
}
