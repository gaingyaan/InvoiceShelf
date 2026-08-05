<?php

namespace App\Domains\Contacts\Http\Controllers\Company;

use App\Domains\Contacts\Application\CustomerService;
use App\Domains\Contacts\Http\Requests\CustomerRequest;
use App\Domains\Contacts\Http\Requests\DeleteCustomersRequest;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomersController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerStatementQuery $customerStatementQuery,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $customers = Customer::with('creator')
            ->whereCompany()
            ->applyFilters($request->all())
            ->paginateData($limit);

        $this->customerStatementQuery->hydrateAccountSummaries(
            $customers instanceof LengthAwarePaginator ? $customers->getCollection() : $customers
        );

        return CustomerResource::collection($customers)
            ->additional(['meta' => [
                'customer_total_count' => Customer::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create(
            attributes: $request->customerAttributes(),
            shippingAddress: $request->shippingAddress(),
            billingAddress: $request->billingAddress(),
            customFields: $request->customFields(),
        );
        $this->customerStatementQuery->hydrateAccountSummaries([$customer]);

        return new CustomerResource($customer);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $this->customerStatementQuery->hydrateAccountSummaries([$customer]);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->update(
            customer: $customer,
            attributes: $request->customerAttributes(),
            shippingAddress: $request->shippingAddress(),
            billingAddress: $request->billingAddress(),
            customFields: $request->customFields(),
        );
        $this->customerStatementQuery->hydrateAccountSummaries([$customer]);

        return new CustomerResource($customer);
    }

    /**
     * Remove a list of Customers along side all their resources (ie. Estimates, Invoices, Payments and Addresses)
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteCustomersRequest $request)
    {
        $this->authorize('delete multiple customers');

        $ids = Customer::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->customerService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
