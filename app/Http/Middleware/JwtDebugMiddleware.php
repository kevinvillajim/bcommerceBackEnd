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
            'env_jwt_ttl' => env('JWT_TTL'),
            'env_jwt_ttl_type' => gettype(env('JWT_TTL')),
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
