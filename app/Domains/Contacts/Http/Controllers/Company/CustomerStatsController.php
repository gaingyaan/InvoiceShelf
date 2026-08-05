<?php

namespace App\Domains\Contacts\Http\Controllers\Company;

use App\Domains\Contacts\Contracts\CustomerStatsProvider;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

class CustomerStatsController extends Controller
{
    public function __construct(
        private readonly CustomerStatsProvider $customerStatsProvider,
        private readonly CustomerStatementQuery $customerStatementQuery,
    ) {}

    public function __invoke(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $chartData = $this->customerStatsProvider->get(
            $customer,
            $request->header('company'),
            $request->has('previous_year')
        );

        $customer = Customer::find($customer->id);
        $this->customerStatementQuery->hydrateAccountSummaries([$customer]);

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'chartData' => $chartData,
            ]]);
    }
}
