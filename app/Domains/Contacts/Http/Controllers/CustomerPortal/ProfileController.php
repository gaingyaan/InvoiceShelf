<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Application\CustomerService;
use App\Domains\Contacts\Contracts\CustomerAvatarManager;
use App\Domains\Contacts\Http\Requests\CustomerPortal\CustomerProfileRequest;
use App\Domains\Contacts\Http\Resources\CustomerPortal\CustomerResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerAvatarManager $customerAvatarManager,
    ) {}

    public function updateProfile(Company $company, CustomerProfileRequest $request)
    {
        $customer = Auth::guard('customer')->user();

        $customer = $this->customerService->updateProfile(
            customer: $customer,
            attributes: $request->customerAttributes(),
            shippingAddress: $request->shippingAddress(),
            billingAddress: $request->billingAddress(),
        );

        if ((bool) $request->validated('is_customer_avatar_removed', false)) {
            $this->customerAvatarManager->clear($customer);
        }

        $avatar = $request->file('customer_avatar');

        if ($avatar) {
            $this->customerAvatarManager->replace(
                $customer,
                $avatar->getPathname(),
                $avatar->getClientOriginalName(),
            );
        }

        return new CustomerResource($customer);
    }

    public function getUser(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        return new CustomerResource($customer);
    }
}
