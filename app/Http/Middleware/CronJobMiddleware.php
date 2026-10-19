<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CronJobMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.cron_job.auth_token');
        if (! empty($token) && hash_equals($token, (string) $request->header('x-authorization-token'))) {
            return $next($request);
        }

        return response()->json(['unauthorized'], 401);
    }
}
