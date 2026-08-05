<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\EnvironmentManager;
use App\Platform\Operations\Installation\Http\Requests\DomainEnvironmentRequest;
use Illuminate\Support\Facades\Artisan;

class AppDomainController extends Controller
{
    public function __invoke(DomainEnvironmentRequest $request)
    {
        Artisan::call('optimize:clear');

        $environmentManager = new EnvironmentManager;

        $results = $environmentManager->saveDomainVariables($request);

        if (in_array('error', $results)) {
            return response()->json($results);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
