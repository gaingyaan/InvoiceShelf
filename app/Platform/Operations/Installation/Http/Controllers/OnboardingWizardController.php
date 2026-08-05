<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingWizardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function getStep(Request $request)
    {
        if (! InstallationState::isDbCreated()) {
            return response()->json([
                'profile_complete' => 0,
                'profile_language' => 'en',
            ]);
        }

        return response()->json([
            'profile_complete' => Setting::getSetting('profile_complete'),
            'profile_language' => Setting::getSetting('profile_language'),
        ]);
    }

    public function updateStep(Request $request)
    {
        $setting = Setting::getSetting('profile_complete');

        if ($setting === 'COMPLETED') {
            return response()->json([
                'profile_complete' => $setting,
            ]);
        }

        Setting::setSetting('profile_complete', $request->profile_complete);

        return response()->json([
            'profile_complete' => Setting::getSetting('profile_complete'),
        ]);
    }

    public function saveLanguage(Request $request)
    {
        Setting::setSetting('profile_language', $request->profile_language);

        return response()->json([
            'profile_language' => Setting::getSetting('profile_language'),
        ]);
    }
}
