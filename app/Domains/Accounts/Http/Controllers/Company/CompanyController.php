<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Contracts\CompanyLogoManager;
use App\Domains\Accounts\Http\Requests\CompanyLogoRequest;
use App\Domains\Accounts\Http\Requests\CompanyRequest;
use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Models\Company;
use App\Platform\Http\Controller;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyAddressWriter $companyAddressWriter,
        private readonly CompanyLogoManager $companyLogoManager,
    ) {}

    public function updateCompany(CompanyRequest $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        $company->update($request->getCompanyPayload());

        $this->companyAddressWriter->upsert($company, (array) $request->input('address'));

        return new CompanyResource($company);
    }

    public function uploadCompanyLogo(CompanyLogoRequest $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        $data = json_decode($request->company_logo);

        if (isset($request->is_company_logo_removed) && (bool) $request->is_company_logo_removed) {
            $this->companyLogoManager->clear($company);
        }
        if ($data) {
            $this->companyLogoManager->replaceBase64($company, $data->data, $data->name);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
