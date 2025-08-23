<?php

namespace App\Http\Middleware;

use App\Services\ConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaintenanceMiddleware
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Check if development mode is enabled
            $developmentMode = $this->configService->getConfig('development.mode', false);

            if (! $developmentMode) {
                // Development mode is disabled, allow normal access
                return $next($request);
            }

            // Development mode is enabled, check access restrictions
            $allowAdminOnlyAccess = $this->configService->getConfig('development.allowAdminOnlyAccess', false);

            if (! $allowAdminOnlyAccess) {
                // Admin-only access is disabled, allow normal access
                return $next($request);
            }

            // Admin-only access is enabled, check if user is admin
            $user = $request->user();

            if (! $user) {
                Log::info('MaintenanceMiddleware: Unauthenticated user blocked in development mode');

                return response()->json([
                    'status' => 'maintenance',
                    'message' => 'El sistema está en modo de mantenimiento. Solo administradores pueden acceder.',
                    'code' => 'MAINTENANCE_MODE',
                ], 503);
            }

            // Check if user is admin
            if (! $user->hasRole('admin')) {
                Log::info('MaintenanceMiddleware: Non-admin user blocked in development mode', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ]);

                return response()->json([
                    'status' => 'maintenance',
                    'message' => 'El sistema está en modo de mantenimiento. Solo administradores pueden acceder.',
                    'code' => 'MAINTENANCE_MODE',
                ], 503);
            }

            // User is admin, allow access
            Log::debug('MaintenanceMiddleware: Admin user allowed access in development mode', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Exception in MaintenanceMiddleware', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // On error, allow access to prevent system lockout
            return $next($request);
        }
    }
}
