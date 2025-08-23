<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleOAuthMiddleware
{
    /**
     * Handle an incoming request for Google OAuth
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Asegurar que las sesiones estén iniciadas
            if (! session()->isStarted()) {
                session()->start();
            }

            Log::info('Google OAuth middleware', [
                'route' => $request->route()->getName(),
                'method' => $request->method(),
                'session_started' => session()->isStarted(),
                'session_id' => session()->getId(),
            ]);

            // Verificar que la sesión esté funcionando
            if (! session()->isStarted()) {
                Log::error('Unable to start session for Google OAuth');

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Session not available',
                        'error' => 'Session store not set on request',
                    ], 500);
                }

                return redirect(env('FRONTEND_URL', 'http://localhost:5173').'/login?error=session_error');
            }

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Error in Google OAuth middleware', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Error in OAuth middleware',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect(env('FRONTEND_URL', 'http://localhost:5173').'/login?error=oauth_error');
        }
    }
}
