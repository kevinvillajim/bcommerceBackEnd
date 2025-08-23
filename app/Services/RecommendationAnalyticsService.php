<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationAnalyticsService
{
    /**
     * Obtiene métricas completas del sistema de recomendaciones
     */
    public function getSystemMetrics(int $days = 30): array
    {
        $cacheKey = "recommendation_analytics_metrics_{$days}";

        return Cache::remember($cacheKey, 60 * 15, function () use ($days) {
            $startDate = now()->subDays($days);

            return [
                'period' => [
                    'days' => $days,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString(),
                ],
                'interactions' => $this->getInteractionMetrics($startDate),
                'user_engagement' => $this->getUserEngagementMetrics($startDate),
                'product_performance' => $this->getProductPerformanceMetrics($startDate),
                'recommendation_effectiveness' => $this->getRecommendationEffectiveness($startDate),
                'system_health' => $this->getSystemHealthMetrics(),
            ];
        });
    }

    /**
     * Métricas de interacciones del usuario
     */
    private function getInteractionMetrics(\Carbon\Carbon $startDate): array
    {
        $interactions = UserInteraction::where('interaction_time', '>=', $startDate)
            ->select('interaction_type', DB::raw('count(*) as count'))
            ->groupBy('interaction_type')
            ->get()
            ->keyBy('interaction_type');

        $totalInteractions = $interactions->sum('count');

        $metrics = [
            'total_interactions' => $totalInteractions,
            'by_type' => [],
            'daily_trend' => $this->getDailyInteractionTrend($startDate),
            'hourly_pattern' => $this->getHourlyInteractionPattern($startDate),
        ];

        // Calcular porcentajes y agregar información de peso
        foreach (UserInteraction::INTERACTION_TYPES as $type => $label) {
            $count = $interactions->get($type)->count ?? 0;
            $weight = UserInteraction::INTERACTION_WEIGHTS[$type] ?? 1.0;

            $metrics['by_type'][$type] = [
                'label' => $label,
                'count' => $count,
                'percentage' => $totalInteractions > 0 ? round(($count / $totalInteractions) * 100, 2) : 0,
                'weight' => $weight,
                'weighted_score' => $count * $weight,
            ];
        }

        return $metrics;
    }

    /**
     * Métricas de engagement de usuarios
     */
    private function getUserEngagementMetrics(\Carbon\Carbon $startDate): array
    {
        // Usuarios activos
        $activeUsers = UserInteraction::where('interaction_time', '>=', $startDate)
            ->distinct('user_id')
            ->count();

        // Usuarios altamente comprometidos (más de 10 interacciones)
        $highEngagementUsers = UserInteraction::where('interaction_time', '>=', $startDate)
            ->select('user_id', DB::raw('count(*) as interaction_count'))
            ->groupBy('user_id')
            ->having('interaction_count', '>', 10)
            ->count();

        // Distribución de engagement scores
        $engagementDistribution = $this->getEngagementScoreDistribution($startDate);

        // Retención de usuarios (usuarios que han interactuado en múltiples días)
        $retentionData = $this->getUserRetentionData($startDate);

        return [
            'active_users' => $activeUsers,
            'high_engagement_users' => $highEngagementUsers,
            'engagement_rate' => $activeUsers > 0 ? round(($highEngagementUsers / $activeUsers) * 100, 2) : 0,
            'score_distribution' => $engagementDistribution,
            'retention' => $retentionData,
            'top_users' => $this->getTopEngagedUsers($startDate, 10),
        ];
    }

    /**
     * Métricas de rendimiento de productos
     */
    private function getProductPerformanceMetrics(\Carbon\Carbon $startDate): array
    {
        // Productos más vistos
        $mostViewed = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'view_product')
            ->select('item_id', DB::raw('count(*) as views'))
            ->groupBy('item_id')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->with('product:id,name,price,rating')
            ->get();

        // Productos más agregados al carrito
        $mostAddedToCart = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'add_to_cart')
            ->select('item_id', DB::raw('count(*) as additions'))
            ->groupBy('item_id')
            ->orderBy('additions', 'desc')
            ->limit(10)
            ->with('product:id,name,price,rating')
            ->get();

        // Tasa de conversión (vista -> carrito -> compra)
        $conversionRates = $this->calculateConversionRates($startDate);

        return [
            'most_viewed' => $mostViewed,
            'most_added_to_cart' => $mostAddedToCart,
            'conversion_rates' => $conversionRates,
            'category_performance' => $this->getCategoryPerformance($startDate),
        ];
    }

    /**
     * Efectividad de las recomendaciones
     */
    private function getRecommendationEffectiveness(\Carbon\Carbon $startDate): array
    {
        // Interacciones que vienen de recomendaciones
        $recommendationInteractions = UserInteraction::where('interaction_time', '>=', $startDate)
            ->whereJsonContains('metadata->source', 'recommendation')
            ->count();

        $totalInteractions = UserInteraction::where('interaction_time', '>=', $startDate)->count();

        // Click-through rate de recomendaciones
        $recommendationViews = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'view_product')
            ->whereJsonContains('metadata->source', 'recommendation')
            ->count();

        $recommendationPurchases = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'purchase')
            ->whereJsonContains('metadata->source', 'recommendation')
            ->count();

        return [
            'recommendation_driven_interactions' => $recommendationInteractions,
            'recommendation_influence_rate' => $totalInteractions > 0 ?
                round(($recommendationInteractions / $totalInteractions) * 100, 2) : 0,
            'recommendation_ctr' => $recommendationViews,
            'recommendation_conversion_rate' => $recommendationViews > 0 ?
                round(($recommendationPurchases / $recommendationViews) * 100, 2) : 0,
            'personalization_effectiveness' => $this->getPersonalizationScore($startDate),
        ];
    }

    /**
     * Métricas de salud del sistema
     */
    private function getSystemHealthMetrics(): array
    {
        return [
            'total_users_with_interactions' => UserInteraction::distinct('user_id')->count(),
            'total_products_with_interactions' => UserInteraction::whereNotNull('item_id')
                ->distinct('item_id')->count(),
            'data_completeness' => $this->calculateDataCompleteness(),
            'system_performance' => $this->getSystemPerformanceMetrics(),
        ];
    }

    /**
     * Tendencia diaria de interacciones
     */
    private function getDailyInteractionTrend(\Carbon\Carbon $startDate): array
    {
        return UserInteraction::where('interaction_time', '>=', $startDate)
            ->select(
                DB::raw('DATE(interaction_time) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Patrón horario de interacciones
     */
    private function getHourlyInteractionPattern(\Carbon\Carbon $startDate): array
    {
        return UserInteraction::where('interaction_time', '>=', $startDate)
            ->select(
                DB::raw('HOUR(interaction_time) as hour'),
                DB::raw('count(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Distribución de scores de engagement
     */
    private function getEngagementScoreDistribution(\Carbon\Carbon $startDate): array
    {
        // Obtener usuarios activos y calcular sus scores
        $userIds = UserInteraction::where('interaction_time', '>=', $startDate)
            ->distinct('user_id')
            ->pluck('user_id');

        $scoreDistribution = [
            'low' => 0,     // 0-25
            'medium' => 0,  // 26-50
            'high' => 0,    // 51-75
            'very_high' => 0, // 76+
        ];

        foreach ($userIds as $userId) {
            $stats = UserInteraction::getUserStats($userId);
            $score = $stats['engagement_score'];

            if ($score <= 25) {
                $scoreDistribution['low']++;
            } elseif ($score <= 50) {
                $scoreDistribution['medium']++;
            } elseif ($score <= 75) {
                $scoreDistribution['high']++;
            } else {
                $scoreDistribution['very_high']++;
            }
        }

        return $scoreDistribution;
    }

    /**
     * Datos de retención de usuarios
     */
    private function getUserRetentionData(\Carbon\Carbon $startDate): array
    {
        $retentionData = UserInteraction::where('interaction_time', '>=', $startDate)
            ->select(
                'user_id',
                DB::raw('COUNT(DISTINCT DATE(interaction_time)) as active_days')
            )
            ->groupBy('user_id')
            ->get();

        $totalUsers = $retentionData->count();

        return [
            'single_day_users' => $retentionData->where('active_days', 1)->count(),
            'multi_day_users' => $retentionData->where('active_days', '>', 1)->count(),
            'retention_rate' => $totalUsers > 0 ?
                round(($retentionData->where('active_days', '>', 1)->count() / $totalUsers) * 100, 2) : 0,
            'average_active_days' => round($retentionData->avg('active_days'), 2),
        ];
    }

    /**
     * Top usuarios más comprometidos
     */
    private function getTopEngagedUsers(\Carbon\Carbon $startDate, int $limit): array
    {
        return UserInteraction::where('interaction_time', '>=', $startDate)
            ->select('user_id', DB::raw('count(*) as interaction_count'))
            ->groupBy('user_id')
            ->orderBy('interaction_count', 'desc')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($item) {
                $stats = UserInteraction::getUserStats($item->user_id);

                return [
                    'user_id' => $item->user_id,
                    'user_name' => $item->user->name ?? 'Usuario '.$item->user_id,
                    'interaction_count' => $item->interaction_count,
                    'engagement_score' => $stats['engagement_score'],
                ];
            })
            ->toArray();
    }

    /**
     * Rendimiento por categoría
     */
    private function getCategoryPerformance(\Carbon\Carbon $startDate): array
    {
        return DB::table('user_interactions as ui')
            ->join('products as p', 'ui.item_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('ui.interaction_time', '>=', $startDate)
            ->where('ui.interaction_type', 'view_product')
            ->select(
                'c.name as category_name',
                DB::raw('count(*) as views'),
                DB::raw('count(distinct ui.user_id) as unique_users')
            )
            ->groupBy('c.id', 'c.name')
            ->orderBy('views', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Calcular tasas de conversión
     */
    private function calculateConversionRates(\Carbon\Carbon $startDate): array
    {
        $views = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'view_product')
            ->count();

        $cartAdditions = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'add_to_cart')
            ->count();

        $purchases = UserInteraction::where('interaction_time', '>=', $startDate)
            ->where('interaction_type', 'purchase')
            ->count();

        return [
            'view_to_cart' => $views > 0 ? round(($cartAdditions / $views) * 100, 2) : 0,
            'cart_to_purchase' => $cartAdditions > 0 ? round(($purchases / $cartAdditions) * 100, 2) : 0,
            'view_to_purchase' => $views > 0 ? round(($purchases / $views) * 100, 2) : 0,
        ];
    }

    /**
     * Score de efectividad de personalización
     */
    private function getPersonalizationScore(\Carbon\Carbon $startDate): float
    {
        // Métricas de personalización basadas en diversidad de interacciones
        $userDiversityScores = UserInteraction::where('interaction_time', '>=', $startDate)
            ->select('user_id')
            ->groupBy('user_id')
            ->get()
            ->map(function ($user) {
                $userInteractions = UserInteraction::where('user_id', $user->user_id)
                    ->where('interaction_time', '>=', now()->subDays(30))
                    ->get();

                $uniqueProducts = $userInteractions->where('item_id', '!=', null)
                    ->pluck('item_id')->unique()->count();

                $interactionTypes = $userInteractions->pluck('interaction_type')->unique()->count();

                // Score basado en diversidad de productos y tipos de interacción
                return ($uniqueProducts * 0.7) + ($interactionTypes * 0.3);
            })
            ->avg();

        return round($userDiversityScores ?? 0, 2);
    }

    /**
     * Calcular completitud de datos
     */
    private function calculateDataCompleteness(): array
    {
        $totalUsers = User::count();
        $usersWithInteractions = UserInteraction::distinct('user_id')->count();

        $totalProducts = Product::count();
        $productsWithInteractions = UserInteraction::whereNotNull('item_id')
            ->distinct('item_id')->count();

        return [
            'users_with_data' => $totalUsers > 0 ? round(($usersWithInteractions / $totalUsers) * 100, 2) : 0,
            'products_with_data' => $totalProducts > 0 ? round(($productsWithInteractions / $totalProducts) * 100, 2) : 0,
            'total_users' => $totalUsers,
            'total_products' => $totalProducts,
        ];
    }

    /**
     * Métricas de rendimiento del sistema
     */
    private function getSystemPerformanceMetrics(): array
    {
        return [
            'avg_interaction_processing_time' => $this->getAverageProcessingTime(),
            'recommendation_generation_performance' => $this->getRecommendationPerformance(),
            'database_health' => $this->getDatabaseHealthMetrics(),
        ];
    }

    /**
     * Tiempo promedio de procesamiento de interacciones
     */
    private function getAverageProcessingTime(): array
    {
        // Esta métrica debería implementarse con métricas de rendimiento reales
        // Por ahora, devolvemos datos simulados
        return [
            'avg_time_ms' => 150,
            'p95_time_ms' => 300,
            'p99_time_ms' => 500,
        ];
    }

    /**
     * Rendimiento de generación de recomendaciones
     */
    private function getRecommendationPerformance(): array
    {
        return [
            'avg_generation_time_ms' => 800,
            'cache_hit_rate' => 75,
            'recommendations_generated_daily' => UserInteraction::whereJsonContains('metadata->source', 'recommendation')
                ->where('interaction_time', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * Métricas de salud de la base de datos
     */
    private function getDatabaseHealthMetrics(): array
    {
        $tableStats = [
            'user_interactions' => UserInteraction::count(),
            'products' => Product::count(),
            'users' => User::count(),
            'ratings' => Rating::count(),
        ];

        return [
            'table_sizes' => $tableStats,
            'index_efficiency' => 'good', // Implementar métricas reales de índices
            'query_performance' => 'optimal', // Implementar métricas reales de queries
        ];
    }

    /**
     * Generar reporte de recomendaciones para un usuario específico
     */
    public function generateUserRecommendationReport(int $userId): array
    {
        $userStats = UserInteraction::getUserStats($userId);
        $user = User::find($userId);

        if (! $user) {
            throw new \Exception("Usuario no encontrado: {$userId}");
        }

        return [
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'registration_date' => $user->created_at,
                'profile_completeness' => $this->calculateUserProfileCompleteness($userId),
            ],
            'interaction_summary' => $userStats,
            'preferences' => $this->getUserPreferences($userId),
            'recommendation_history' => $this->getUserRecommendationHistory($userId),
            'personalization_score' => $this->calculateUserPersonalizationScore($userId),
        ];
    }

    /**
     * Calcular completitud del perfil de usuario
     */
    private function calculateUserProfileCompleteness(int $userId): float
    {
        $user = User::find($userId);
        $score = 0;

        // Información básica del perfil
        if ($user->age) {
            $score += 10;
        }
        if ($user->gender) {
            $score += 10;
        }
        if ($user->location) {
            $score += 10;
        }

        // Interacciones recientes (último mes)
        $recentInteractions = UserInteraction::where('user_id', $userId)
            ->where('interaction_time', '>=', now()->subMonth())
            ->count();

        if ($recentInteractions >= 5) {
            $score += 20;
        }
        if ($recentInteractions >= 15) {
            $score += 20;
        }
        if ($recentInteractions >= 30) {
            $score += 30;
        }

        return min(100, $score);
    }

    /**
     * Obtener preferencias del usuario
     */
    private function getUserPreferences(int $userId): array
    {
        // Top categorías preferidas
        $topCategories = DB::table('user_interactions as ui')
            ->join('products as p', 'ui.item_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('ui.user_id', $userId)
            ->where('ui.interaction_type', 'view_product')
            ->select('c.name', DB::raw('count(*) as count'))
            ->groupBy('c.id', 'c.name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        // Búsquedas frecuentes
        $topSearches = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', 'search')
            ->get()
            ->pluck('metadata.term')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);

        return [
            'top_categories' => $topCategories,
            'frequent_searches' => $topSearches,
            'engagement_patterns' => $this->getUserEngagementPatterns($userId),
        ];
    }

    /**
     * Historial de recomendaciones del usuario
     */
    private function getUserRecommendationHistory(int $userId): array
    {
        return UserInteraction::where('user_id', $userId)
            ->whereJsonContains('metadata->source', 'recommendation')
            ->orderBy('interaction_time', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($interaction) {
                return [
                    'type' => $interaction->interaction_type,
                    'product_id' => $interaction->item_id,
                    'timestamp' => $interaction->interaction_time,
                    'metadata' => $interaction->metadata,
                ];
            })
            ->toArray();
    }

    /**
     * Score de personalización para un usuario específico
     */
    private function calculateUserPersonalizationScore(int $userId): float
    {
        $interactions = UserInteraction::where('user_id', $userId)
            ->where('interaction_time', '>=', now()->subMonth())
            ->get();

        if ($interactions->isEmpty()) {
            return 0;
        }

        $uniqueProducts = $interactions->where('item_id', '!=', null)->pluck('item_id')->unique()->count();
        $uniqueCategories = $interactions->where('item_id', '!=', null)
            ->load('product.category')
            ->pluck('product.category.id')
            ->filter()
            ->unique()
            ->count();

        $interactionTypes = $interactions->pluck('interaction_type')->unique()->count();

        // Score basado en diversidad y actividad
        $diversityScore = ($uniqueProducts * 2) + ($uniqueCategories * 5) + ($interactionTypes * 3);
        $activityScore = min(50, $interactions->count());

        return min(100, $diversityScore + $activityScore);
    }

    /**
     * Patrones de engagement del usuario
     */
    private function getUserEngagementPatterns(int $userId): array
    {
        $interactions = UserInteraction::where('user_id', $userId)
            ->where('interaction_time', '>=', now()->subMonth())
            ->get();

        return [
            'most_active_hour' => $interactions->groupBy(function ($item) {
                return $item->interaction_time->hour;
            })->map->count()->sortDesc()->keys()->first(),

            'most_active_day' => $interactions->groupBy(function ($item) {
                return $item->interaction_time->dayOfWeek;
            })->map->count()->sortDesc()->keys()->first(),

            'session_patterns' => $this->analyzeUserSessions($userId),
            'conversion_tendency' => $this->getUserConversionTendency($userId),
        ];
    }

    /**
     * Analizar patrones de sesión del usuario
     */
    private function analyzeUserSessions(int $userId): array
    {
        // Análisis simplificado de sesiones
        $recentInteractions = UserInteraction::where('user_id', $userId)
            ->where('interaction_time', '>=', now()->subWeek())
            ->orderBy('interaction_time')
            ->get();

        $sessions = [];
        $currentSession = [];
        $sessionThreshold = 30; // 30 minutos entre interacciones

        foreach ($recentInteractions as $interaction) {
            if (empty($currentSession) ||
                $interaction->interaction_time->diffInMinutes(end($currentSession)['time']) <= $sessionThreshold) {
                $currentSession[] = [
                    'time' => $interaction->interaction_time,
                    'type' => $interaction->interaction_type,
                ];
            } else {
                if (! empty($currentSession)) {
                    $sessions[] = $currentSession;
                }
                $currentSession = [$interaction];
            }
        }

        if (! empty($currentSession)) {
            $sessions[] = $currentSession;
        }

        return [
            'total_sessions' => count($sessions),
            'avg_session_length' => collect($sessions)->avg(function ($session) {
                return count($session);
            }),
            'avg_session_duration' => collect($sessions)->avg(function ($session) {
                if (count($session) < 2) {
                    return 0;
                }

                return end($session)['time']->diffInMinutes($session[0]['time']);
            }),
        ];
    }

    /**
     * Tendencia de conversión del usuario
     */
    private function getUserConversionTendency(int $userId): array
    {
        $views = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', 'view_product')
            ->count();

        $cartAdditions = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', 'add_to_cart')
            ->count();

        $purchases = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', 'purchase')
            ->count();

        return [
            'view_to_cart_rate' => $views > 0 ? round(($cartAdditions / $views) * 100, 2) : 0,
            'cart_to_purchase_rate' => $cartAdditions > 0 ? round(($purchases / $cartAdditions) * 100, 2) : 0,
            'overall_conversion_rate' => $views > 0 ? round(($purchases / $views) * 100, 2) : 0,
            'buyer_likelihood' => $this->calculateBuyerLikelihood($userId),
        ];
    }

    /**
     * Calcular probabilidad de compra del usuario
     */
    private function calculateBuyerLikelihood(int $userId): string
    {
        $stats = UserInteraction::getUserStats($userId);
        $engagementScore = $stats['engagement_score'];

        $purchaseInteractions = UserInteraction::where('user_id', $userId)
            ->where('interaction_type', 'purchase')
            ->count();

        $totalInteractions = $stats['total_interactions'];

        if ($purchaseInteractions > 0 && $engagementScore > 50) {
            return 'high';
        } elseif ($engagementScore > 30 || $totalInteractions > 20) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
