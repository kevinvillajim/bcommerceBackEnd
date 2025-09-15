<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JwtDebugMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Log JWT-related configuration
        Log::info('JWT Configuration Debug', [
            'jwt.ttl' => config('jwt.ttl'),
            'jwt.ttl_type' => gettype(config('jwt.ttl')),
            'session_timeout_minutes' => env('SESSION_TIMEOUT_MINUTES'),
            'session_timeout_config' => config('session_timeout.ttl'),
            'centralized_source' => 'session_timeout.php',
        ]);

        // Additional debug for request
        Log::info('Request Debug', [
            'path' => $request->path(),
            'method' => $request->method(),
            'all_input' => $request->all(),
        ]);

        return $next($request);
    }
}
