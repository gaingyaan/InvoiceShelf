<?php

namespace App\Platform\Operations\Installation\Http\Middleware;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! InstallationState::isDbCreated() || Setting::getSetting('profile_complete') !== 'COMPLETED') {
                return redirect('/installation');
            }
        } catch (\Exception $e) {
            return redirect('/installation');
        }

        return $next($request);
    }
}
