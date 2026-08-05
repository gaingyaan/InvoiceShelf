<?php

namespace App\Http\Controllers\Admin\Modules;

use App\Events\ModuleDisabledEvent;
use App\Events\ModuleEnabledEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module as ModelsModule;
use App\Services\Marketplace\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nwidart\Modules\Facades\Module;

class ModulesController extends Controller
{
    public function index(MarketplaceClient $client)
    {
        $this->authorize('manage modules');

        $response = $client->catalog();
        $body = $response->json();
        $modules = is_array($body) ? ($body['modules'] ?? $body['data'] ?? null) : null;

        if (! $response->successful() || ! is_array($modules)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return ModuleResource::collection(collect($modules));
    }

    public function show(string $module, MarketplaceClient $client)
    {
        $this->authorize('manage modules');

        $response = $client->module($module);
        $body = $response->json();

        if ($response->status() === 404) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if (! $response->successful() || ! is_array($body) || ! is_array($body['module'] ?? null)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        return (new ModuleResource($body['module']))
            ->additional(['meta' => [
                'modules' => ModuleResource::collection(
                    collect($body['meta']['modules'] ?? [])
                ),
            ]]);
    }

    public function enable(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::where('name', $module)->first();
        $module->update(['enabled' => true]);
        $installedModule = Module::find($module->name);
        $installedModule->enable();

        ModuleEnabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }

    public function disable(Request $request, string $module): JsonResponse
    {
        $this->authorize('manage modules');

        $module = ModelsModule::where('name', $module)->first();
        $module->update(['enabled' => false]);
        $installedModule = Module::find($module->name);
        $installedModule->disable();

        ModuleDisabledEvent::dispatch($module);

        return response()->json(['success' => true]);
    }
}
