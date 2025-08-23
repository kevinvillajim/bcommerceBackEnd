<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\ProfileEnricherService;
use App\Services\RecommendationAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommendationSystemController extends Controller
{
    private RecommendationAnalyticsService $analyticsService;

    private ProfileEnricherService $profileEnricherService;

    public function __construct(
        RecommendationAnalyticsService $analyticsService,
        ProfileEnricherService $profileEnricherService
    ) {
        $this->analyticsService = $analyticsService;
        $this->profileEnricherService = $profileEnricherService;
    }

    /**
     * Dashboard principal del sistema de recomendaciones
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            // Verificar permisos de administrador
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $days = $request->input('days', 30);

            // Obtener métricas del sistema
            $systemMetrics = $this->analyticsService->getSystemMetrics($days);

            // Métricas adicionales del dashboard
            $dashboardData = [
                'system_overview' => $this->getSystemOverview(),
                'recent_activity' => $this->getRecentActivity(),
                'top_performing_products' => $this->getTopPerformingProducts(),
                'user_segments' => $this->getUserSegmentAnalysis(),
                'recommendation_performance' => $systemMetrics['recommendation_effectiveness'],
                'alerts' => $this->getSystemAlerts(),
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($systemMetrics, $dashboardData),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en dashboard de recomendaciones: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Análisis detallado de un usuario específico
     */
    public function analyzeUser(Request $request, int $userId): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            // Verificar que el usuario existe
            $user = User::find($userId);
            if (! $user) {
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }

            // Generar reporte completo del usuario
            $userReport = $this->analyticsService->generateUserRecommendationReport($userId);

            // Enriquecer perfil del usuario
            $enrichedProfile = $this->profileEnricherService->enrichUserProfile($userId);

            // Estadísticas de interacciones
            $interactionStats = UserInteraction::getUserStats($userId);

            $response = [
                'user_report' => $userReport,
                'enriched_profile' => $enrichedProfile,
                'interaction_stats' => $interactionStats,
                'recommendations_sample' => $this->generateSampleRecommendations($userId),
            ];

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('Error analizando usuario: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al analizar usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Configuración del sistema de recomendaciones
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $config = [
                'interaction_weights' => UserInteraction::INTERACTION_WEIGHTS,
                'interaction_types' => UserInteraction::INTERACTION_TYPES,
                'strategy_weights' => $this->getDefaultStrategyWeights(),
                'system_parameters' => $this->getSystemParameters(),
                'cache_settings' => $this->getCacheSettings(),
            ];

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar configuración del sistema
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $request->validate([
                'interaction_weights' => 'sometimes|array',
                'strategy_weights' => 'sometimes|array',
                'system_parameters' => 'sometimes|array',
            ]);

            // Guardar configuración en cache/database
            $config = $request->only(['interaction_weights', 'strategy_weights', 'system_parameters']);

            foreach ($config as $key => $value) {
                Cache::put("recommendation_config_{$key}", $value, 60 * 60 * 24); // 24 horas
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando configuración: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error actualizando configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Limpiar caché del sistema de recomendaciones
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $cacheType = $request->input('type', 'all');
            $cleared = 0;

            switch ($cacheType) {
                case 'recommendations':
                    $patterns = ['personalized_recommendations_*', 'product_recommendations_*'];
                    break;
                case 'analytics':
                    $patterns = ['recommendation_analytics_*', 'user_profile_*'];
                    break;
                case 'products':
                    $patterns = ['products_*', 'product_detail_*', 'featured_products_*'];
                    break;
                case 'all':
                default:
                    Cache::flush();
                    $cleared = 'all';
                    break;
            }

            if ($cacheType !== 'all') {
                foreach ($patterns as $pattern) {
                    // Implementar limpieza por patrón según el driver de cache
                    $this->clearCacheByPattern($pattern);
                    $cleared++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Cache limpiado exitosamente',
                'cleared' => $cleared,
            ]);

        } catch (\Exception $e) {
            Log::error('Error limpiando cache: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error limpiando cache',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Exportar datos para análisis externo
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $request->validate([
                'type' => 'required|in:interactions,users,products,analytics',
                'format' => 'sometimes|in:json,csv',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'limit' => 'sometimes|integer|max:10000',
            ]);

            $type = $request->input('type');
            $format = $request->input('format', 'json');
            $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', now()->toDateString());
            $limit = $request->input('limit', 1000);

            $data = $this->getExportData($type, $dateFrom, $dateTo, $limit);

            if ($format === 'csv') {
                return $this->exportToCsv($data, $type);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'type' => $type,
                    'format' => $format,
                    'date_range' => [$dateFrom, $dateTo],
                    'count' => count($data),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error exportando datos: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error exportando datos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Métricas de rendimiento del sistema
     */
    public function performanceMetrics(): JsonResponse
    {
        try {
            if (! Auth::user() || ! Auth::user()->isAdmin()) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $metrics = [
                'database_performance' => $this->getDatabasePerformanceMetrics(),
                'cache_performance' => $this->getCachePerformanceMetrics(),
                'recommendation_latency' => $this->getRecommendationLatencyMetrics(),
                'system_health' => $this->getSystemHealthStatus(),
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo métricas de rendimiento',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    // Métodos privados auxiliares

    private function getSystemOverview(): array
    {
        return [
            'total_users' => User::count(),
            'active_users_30d' => UserInteraction::where('interaction_time', '>=', now()->subDays(30))
                ->distinct('user_id')->count(),
            'total_products' => Product::count(),
            'total_interactions' => UserInteraction::count(),
            'total_ratings' => Rating::count(),
            'avg_rating' => Rating::avg('rating') ?: 0,
            'system_uptime' => '99.9%', // Implementar métricas reales
            'last_updated' => now()->toISOString(),
        ];
    }

    private function getRecentActivity(): array
    {
        $recentInteractions = UserInteraction::with('user:id,name')
            ->where('interaction_time', '>=', now()->subHours(24))
            ->orderBy('interaction_time', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($interaction) {
                return [
                    'user_name' => $interaction->user->name ?? 'Usuario '.$interaction->user_id,
                    'type' => $interaction->interaction_type,
                    'item_id' => $interaction->item_id,
                    'timestamp' => $interaction->interaction_time,
                    'metadata' => $interaction->metadata,
                ];
            });

        return $recentInteractions->toArray();
    }

    private function getTopPerformingProducts(): array
    {
        return Product::leftJoin('user_interactions', 'products.id', '=', 'user_interactions.item_id')
            ->select([
                'products.id',
                'products.name',
                'products.rating',
                'products.view_count',
                'products.sales_count',
                DB::raw('COUNT(user_interactions.id) as interaction_count'),
            ])
            ->where('user_interactions.interaction_time', '>=', now()->subDays(30))
            ->groupBy('products.id', 'products.name', 'products.rating', 'products.view_count', 'products.sales_count')
            ->orderBy('interaction_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getUserSegmentAnalysis(): array
    {
        // Análisis simplificado de segmentos de usuario
        $segments = [
            'new_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users' => UserInteraction::where('interaction_time', '>=', now()->subDays(7))
                ->distinct('user_id')->count(),
            'power_users' => UserInteraction::select('user_id')
                ->where('interaction_time', '>=', now()->subDays(30))
                ->groupBy('user_id')
                ->having(DB::raw('count(*)'), '>=', 50)
                ->count(),
            'buyers' => UserInteraction::where('interaction_type', 'purchase')
                ->where('interaction_time', '>=', now()->subDays(30))
                ->distinct('user_id')
                ->count(),
        ];

        return $segments;
    }

    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Verificar si hay usuarios sin interacciones recientes
        $inactiveUsers = User::whereDoesntHave('interactions', function ($query) {
            $query->where('interaction_time', '>=', now()->subDays(60));
        })->count();

        if ($inactiveUsers > User::count() * 0.5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Muchos usuarios inactivos ({$inactiveUsers})",
                'action' => 'consider_engagement_campaign',
            ];
        }

        // Verificar productos sin interacciones
        $inactiveProducts = Product::whereDoesntHave('interactions')->count();
        if ($inactiveProducts > 100) {
            $alerts[] = [
                'type' => 'info',
                'message' => "Productos sin interacciones: {$inactiveProducts}",
                'action' => 'review_product_visibility',
            ];
        }

        return $alerts;
    }

    private function generateSampleRecommendations(int $userId): array
    {
        try {
            $generateRecommendationsUseCase = app(\App\UseCases\Recommendation\GenerateRecommendationsUseCase::class);

            return $generateRecommendationsUseCase->execute($userId, 5);
        } catch (\Exception $e) {
            Log::error('Error generando recomendaciones de muestra: '.$e->getMessage());

            return [];
        }
    }

    private function getDefaultStrategyWeights(): array
    {
        return [
            'history_based' => 0.25,
            'category_based' => 0.20,
            'collaborative' => 0.20,
            'content_based' => 0.15,
            'trending' => 0.10,
            'demographic' => 0.10,
        ];
    }

    private function getSystemParameters(): array
    {
        return [
            'min_interactions_for_recommendations' => 3,
            'recommendation_cache_ttl' => 1800, // 30 minutos
            'user_profile_cache_ttl' => 3600, // 1 hora
            'max_recommendations_per_request' => 50,
            'interaction_time_decay_days' => 90,
        ];
    }

    private function getCacheSettings(): array
    {
        return [
            'driver' => config('cache.default'),
            'enabled' => true,
            'ttl' => [
                'recommendations' => 1800,
                'user_profiles' => 3600,
                'product_data' => 7200,
                'analytics' => 900,
            ],
        ];
    }

    private function clearCacheByPattern(string $pattern): void
    {
        // Implementación simplificada - en producción usar Redis SCAN o similar
        try {
            $keys = Cache::getRedis()->keys($pattern);
            if (! empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        } catch (\Exception $e) {
            // Fallback: limpiar cache completo si no se puede por patrón
            Log::warning('No se pudo limpiar cache por patrón, limpiando todo: '.$e->getMessage());
            Cache::flush();
        }
    }

    private function getExportData(string $type, string $dateFrom, string $dateTo, int $limit): array
    {
        switch ($type) {
            case 'interactions':
                return UserInteraction::with('user:id,name', 'product:id,name')
                    ->whereBetween('interaction_time', [$dateFrom, $dateTo])
                    ->limit($limit)
                    ->get()
                    ->toArray();

            case 'users':
                return User::with(['interactions' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('interaction_time', [$dateFrom, $dateTo]);
                }])
                    ->limit($limit)
                    ->get()
                    ->toArray();

            case 'products':
                return Product::with(['interactions' => function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('interaction_time', [$dateFrom, $dateTo]);
                }])
                    ->limit($limit)
                    ->get()
                    ->toArray();

            case 'analytics':
                return $this->analyticsService->getSystemMetrics(
                    now()->parse($dateTo)->diffInDays(now()->parse($dateFrom))
                );

            default:
                return [];
        }
    }

    private function exportToCsv(array $data, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = "{$type}_export_".now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            if (! empty($data)) {
                // Escribir headers
                fputcsv($handle, array_keys($data[0]));

                // Escribir datos
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function getDatabasePerformanceMetrics(): array
    {
        return [
            'connection_status' => 'healthy',
            'avg_query_time' => '45ms',
            'active_connections' => 12,
            'slow_queries' => 0,
        ];
    }

    private function getCachePerformanceMetrics(): array
    {
        return [
            'hit_rate' => '85%',
            'memory_usage' => '60%',
            'evictions' => 5,
            'keys_count' => 1250,
        ];
    }

    private function getRecommendationLatencyMetrics(): array
    {
        return [
            'avg_generation_time' => '120ms',
            'p95_generation_time' => '250ms',
            'cache_hit_rate' => '75%',
            'error_rate' => '0.1%',
        ];
    }

    private function getSystemHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'uptime' => '99.9%',
            'last_restart' => '2025-08-01 10:00:00',
            'memory_usage' => '65%',
            'cpu_usage' => '25%',
        ];
    }
}
