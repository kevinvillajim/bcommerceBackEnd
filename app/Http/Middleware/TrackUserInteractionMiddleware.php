<?php

namespace App\Http\Middleware;

use App\Models\UserInteraction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackUserInteractionMiddleware
{
    /**
     * Middleware para tracking automático de interacciones de usuario
     *
     * Puede configurarse con parámetros para diferentes tipos de tracking:
     * - track_type: el tipo de interacción a registrar
     * - extract_from: de dónde extraer el item_id (route, query, etc.)
     *
     * Uso en routes:
     * Route::get('/products/{id}', [ProductController::class, 'show'])
     *   ->middleware('track.interaction:view_product,route.id');
     */
    public function handle(Request $request, Closure $next, ?string $trackType = null, ?string $extractFrom = null): Response
    {
        // Ejecutar el request primero
        $response = $next($request);

        // Solo trackear si hay usuario autenticado y la respuesta es exitosa
        if (! Auth::check() || ! $response->isSuccessful()) {
            return $response;
        }

        try {
            $this->trackInteraction($request, $response, $trackType, $extractFrom);
        } catch (\Exception $e) {
            // No fallar el request por errores de tracking
            Log::error('Error en TrackUserInteractionMiddleware', [
                'error' => $e->getMessage(),
                'track_type' => $trackType,
                'extract_from' => $extractFrom,
                'url' => $request->url(),
            ]);
        }

        return $response;
    }

    /**
     * Registra la interacción del usuario
     */
    private function trackInteraction(Request $request, Response $response, ?string $trackType, ?string $extractFrom): void
    {
        $userId = Auth::id();

        if (! $userId || ! $trackType) {
            return;
        }

        // Extraer item_id según la configuración
        $itemId = $this->extractItemId($request, $extractFrom);

        // Preparar metadata con información del contexto
        $metadata = $this->buildMetadata($request, $response);

        // Evitar duplicados recientes (mismo usuario, tipo, item en los últimos 5 minutos)
        if ($this->isDuplicate($userId, $trackType, $itemId, 5)) {
            return;
        }

        // Registrar la interacción
        UserInteraction::track($userId, $trackType, $itemId, $metadata);

        Log::info('🎯 [MIDDLEWARE TRACK] Interacción registrada automáticamente', [
            'user_id' => $userId,
            'track_type' => $trackType,
            'item_id' => $itemId,
            'url' => $request->url(),
        ]);
    }

    /**
     * Extrae el item_id según la configuración
     */
    private function extractItemId(Request $request, ?string $extractFrom): ?int
    {
        if (! $extractFrom) {
            return null;
        }

        // Formato: "route.parameter_name" o "query.parameter_name"
        $parts = explode('.', $extractFrom, 2);
        $source = $parts[0];
        $parameter = $parts[1] ?? 'id';

        switch ($source) {
            case 'route':
                return (int) $request->route($parameter) ?: null;

            case 'query':
                return (int) $request->query($parameter) ?: null;

            case 'body':
                return (int) $request->input($parameter) ?: null;

            default:
                return null;
        }
    }

    /**
     * Construye metadata contextual para la interacción
     */
    private function buildMetadata(Request $request, Response $response): array
    {
        $metadata = [
            'url' => $request->url(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'referer' => $request->header('referer'),
            'response_status' => $response->getStatusCode(),
            'timestamp' => now()->toISOString(),
        ];

        // Agregar información específica según el tipo de request

        // Para requests de productos, capturar información adicional
        if (str_contains($request->path(), 'products')) {
            $metadata['context'] = 'product_interaction';

            // Si es una vista de producto, intentar capturar tiempo de página anterior
            if ($request->header('referer')) {
                $metadata['referrer_type'] = $this->classifyReferrer($request->header('referer'));
            }
        }

        // Para búsquedas, capturar parámetros de búsqueda
        if (str_contains($request->path(), 'search') || $request->has('search')) {
            $metadata['context'] = 'search_interaction';
            $metadata['search_params'] = [
                'term' => $request->input('term', $request->input('search')),
                'filters' => $request->except(['term', 'search', 'page', 'limit']),
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 12),
            ];
        }

        // Para navegación de categorías
        if (str_contains($request->path(), 'categories')) {
            $metadata['context'] = 'category_interaction';
            $metadata['category_params'] = $request->query();
        }

        return $metadata;
    }

    /**
     * Clasifica el tipo de referrer para entender el flujo del usuario
     */
    private function classifyReferrer(string $referer): string
    {
        if (str_contains($referer, 'search')) {
            return 'search_results';
        } elseif (str_contains($referer, 'category')) {
            return 'category_browse';
        } elseif (str_contains($referer, 'recommendations')) {
            return 'recommendations';
        } elseif (str_contains($referer, 'product')) {
            return 'product_page';
        } elseif (str_contains($referer, 'home') || str_contains($referer, '/')) {
            return 'home_page';
        } else {
            return 'external';
        }
    }

    /**
     * Verifica si es una interacción duplicada reciente
     */
    private function isDuplicate(int $userId, string $trackType, ?int $itemId, int $minutesThreshold): bool
    {
        $query = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', $trackType)
            ->where('interaction_time', '>=', now()->subMinutes($minutesThreshold));

        if ($itemId) {
            $query->where('item_id', $itemId);
        } else {
            $query->whereNull('item_id');
        }

        return $query->exists();
    }
}
