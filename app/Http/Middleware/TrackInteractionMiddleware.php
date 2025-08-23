<?php

namespace App\Http\Middleware;

use App\Models\UserInteraction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para tracking automático de interacciones de usuario
 *
 * Registra automáticamente las interacciones cuando los usuarios navegan por el sitio
 */
class TrackInteractionMiddleware
{
    /**
     * Maneja una solicitud entrante y registra la interacción
     */
    public function handle(Request $request, Closure $next, string $interactionType = '', ?string $itemSource = null): Response
    {
        try {
            // Procesar la request primero
            $response = $next($request);

            // Log simple para debug
            Log::info('✅ TrackInteractionMiddleware ejecutado exitosamente', [
                'path' => $request->path(),
                'interaction_type' => $interactionType,
                'user_authenticated' => Auth::check(),
                'status_code' => $response->getStatusCode(),
            ]);

            // Solo registrar interacciones para usuarios autenticados y respuestas exitosas
            if (Auth::check() && $response->getStatusCode() < 400) {
                $this->trackInteraction($request, $interactionType, $itemSource);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('❌ Error en TrackInteractionMiddleware', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_path' => $request->path(),
                'interaction_type' => $interactionType,
                'trace' => $e->getTraceAsString(),
            ]);

            // NO fallar la request por errores de tracking - procesar normalmente
            return $next($request);
        }
    }

    /**
     * Registra la interacción del usuario
     */
    private function trackInteraction(Request $request, string $interactionType, ?string $itemSource): void
    {
        try {
            $userId = Auth::id();
            $itemId = $this->extractItemId($request, $itemSource);
            $metadata = $this->buildMetadata($request, $interactionType);

            // Solo registrar si tenemos datos válidos
            if ($userId && $interactionType) {
                UserInteraction::track($userId, $interactionType, $itemId, $metadata);

                Log::info('🎯 Interacción registrada automáticamente', [
                    'user_id' => $userId,
                    'type' => $interactionType,
                    'item_id' => $itemId,
                    'path' => $request->path(),
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('⚠️ Error registrando interacción automática', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'interaction_type' => $interactionType,
            ]);
        }
    }

    /**
     * Extrae el ID del item de la request
     */
    private function extractItemId(Request $request, ?string $itemSource): ?int
    {
        if (! $itemSource) {
            return null;
        }

        // Formato: "route.parameterName" o "query.parameterName"
        $parts = explode('.', $itemSource);
        if (count($parts) !== 2) {
            return null;
        }

        [$source, $parameter] = $parts;

        switch ($source) {
            case 'route':
                $value = $request->route($parameter);
                break;
            case 'query':
                $value = $request->query($parameter);
                break;
            default:
                return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Construye metadata adicional para la interacción
     */
    private function buildMetadata(Request $request, string $interactionType): array
    {
        $metadata = [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
        ];

        // Metadata específica por tipo de interacción
        switch ($interactionType) {
            case 'search':
                if ($request->has('term') || $request->route('term')) {
                    $metadata['term'] = $request->get('term') ?? $request->route('term');
                }
                break;

            case 'browse_category':
                if ($request->route('categoryId')) {
                    $metadata['category_id'] = $request->route('categoryId');
                }
                break;

            case 'view_product':
                if ($request->route('id')) {
                    $metadata['product_id'] = $request->route('id');
                }
                if ($request->route('slug')) {
                    $metadata['product_slug'] = $request->route('slug');
                }
                // Por defecto, asignar 30 segundos de tiempo de vista
                $metadata['view_time'] = 30;
                break;
        }

        return $metadata;
    }
}
