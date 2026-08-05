<?php

namespace App\Domains\Receivables\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Receivables\Http\Resources\CustomerPortal\PaymentMethodResource;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentMethodController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Company $company)
    {
        return PaymentMethodResource::collection(PaymentMethod::where('company_id', $company->id)->get());
    }
}
