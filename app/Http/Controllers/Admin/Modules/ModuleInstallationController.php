<?php

namespace App\Http\Controllers\Admin\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallMarketplaceModuleRequest;
use App\Services\Marketplace\MarketplaceInstaller;
use Illuminate\Http\JsonResponse;

class ModuleInstallationController extends Controller
{
    public function install(InstallMarketplaceModuleRequest $request, MarketplaceInstaller $installer): JsonResponse
    {
        $this->authorize('manage modules');

        $response = $installer->install(
            $request->string('slug')->toString(),
            $request->string('version')->toString(),
            $request->string('channel')->toString() ?: (string) config('invoiceshelf.marketplace.channel', 'stable'),
        );

        return response()->json($response, $response['success'] ? 200 : 422);
    }
}
