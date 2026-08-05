<?php

namespace App\Platform\Operations\Http\Admin;

use App\Platform\Http\Controller;
use App\Platform\Operations\Http\Requests\GetSettingRequest;
use App\Platform\Operations\Http\Requests\SettingRequest;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function show(GetSettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $setting = Setting::getSetting($request->key);

        return response()->json([
            $request->key => $setting,
        ]);
    }

    public function update(SettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        Setting::setSettings($request->settings);

        return response()->json([
            'success' => true,
            $request->settings,
        ]);
    }
}
