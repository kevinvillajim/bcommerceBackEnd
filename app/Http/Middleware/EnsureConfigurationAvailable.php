<?php

namespace App\Http\Middleware;

use App\Services\ConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Configuration is Available Middleware
 * 
 * This middleware ensures that the configuration system is working properly
 * before allowing requests to proceed to certain critical endpoints.
 */
class EnsureConfigurationAvailable
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $configService = app(ConfigurationService::class);
            $diagnostics = $configService->getDiagnostics();
            
            // Log diagnostics for debugging
            Log::debug('Configuration middleware diagnostics', $diagnostics);
            
            // Check if we're in a critical path that requires database
            $criticalPaths = [
                'admin/configurations',
                'admin/settings',
            ];
            
            $isCriticalPath = false;
            foreach ($criticalPaths as $path) {
                if ($request->is("api/{$path}/*")) {
                    $isCriticalPath = true;
                    break;
                }
            }
            
            // If it's a critical path and database is not available, return error
            if ($isCriticalPath && !$diagnostics['database_available']) {
                Log::error('Configuration database not available for critical path', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration system temporarily unavailable',
                    'error' => 'Database connection required for this operation',
                ], 503);
            }
            
            // For non-critical paths, just log a warning if database is not available
            if (!$diagnostics['database_available']) {
                Log::warning('Configuration database not available, using fallbacks', [
                    'path' => $request->path(),
                    'cache_available' => $diagnostics['cache_available'],
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Configuration middleware error', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
            
            // For non-critical operations, allow to proceed with defaults
            // For critical operations, fail safely
            if (isset($isCriticalPath) && $isCriticalPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration system error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }
        }
        
        return $next($request);
    }
}