<?php

namespace App\Platform\Storage\Http\Middleware;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Storage\Models\FileDisk;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isDbCreated()) {
            // Only handle dynamic file disk switching when file_disk_id is provided
            if ($request->has('file_disk_id')) {
                $file_disk = FileDisk::find($request->file_disk_id);

                if ($file_disk) {
                    $file_disk->setConfig();
                }
            }
            // The default file disk is applied during application bootstrap.
        }

        return $next($request);
    }
}
