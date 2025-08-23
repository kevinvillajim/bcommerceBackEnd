<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware simplificado para tracking de interacciones
 * Versión de respaldo que no debería causar errores
 */
class TrackInteractionMiddlewareSimple
{
    /**
     * Maneja una solicitud entrante.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Procesar la request normalmente primero
        $response = $next($request);

        try {
            // Log simple para debug
            Log::info('🎯 [SIMPLE-TRACK] Request procesada', [
                'route' => $request->path(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
            ]);
        } catch (\Exception $e) {
            // Silenciosamente continuar si hay error
            Log::warning('⚠️ [SIMPLE-TRACK] Error en tracking simple', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
