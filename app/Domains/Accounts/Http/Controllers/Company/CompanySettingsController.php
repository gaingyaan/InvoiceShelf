<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Http\Requests\GetSettingsRequest;
use App\Domains\Accounts\Http\Requests\UpdateSettingsRequest;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Silber\Bouncer\BouncerFacade;

class CompanySettingsController extends Controller
{
    public function show(GetSettingsRequest $request): JsonResponse
    {
        $settings = CompanySetting::getSettings((array) $request->settings, $request->header('company'));

        return response()->json($settings);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $company = Company::find($request->header('company'));
        $this->authorize('manage company', $company);

        $data = $request->settings;

        if (
            Arr::exists($data, 'currency') &&
            (CompanySetting::getSetting('currency', $company->id) !== $data['currency']) &&
            $company->hasTransactions()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update company currency after transactions are created.',
            ]);
        }

        CompanySetting::setSettings($data, $request->header('company'));

        return response()->json([
            'success' => true,
        ]);
    }

    public function checkTransactions(Request $request): JsonResponse
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        return response()->json([
            'has_transactions' => $company->hasTransactions(),
        ]);
    }

    public function transferOwnership(Request $request, User $user): JsonResponse
    {
        $company = Company::find($request->header('company'));
        $this->authorize('transfer company ownership', $company);

        if (! $user->hasCompany($company->id)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to this company.',
            ]);
        }

        $company->update(['owner_id' => $user->id]);
        BouncerFacade::scope()->to($company->id);
        BouncerFacade::sync($user)->roles(['owner']);

        return response()->json([
            'success' => true,
        ]);
    }
}
