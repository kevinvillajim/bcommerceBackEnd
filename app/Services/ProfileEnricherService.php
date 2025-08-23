<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileEnricherService
{
    /**
     * Enriquece el perfil de usuario usando reglas heurísticas inteligentes
     * Similar a los sistemas de Netflix, Amazon, MercadoLibre
     */
    public function enrichUserProfile(int $userId): array
    {
        try {
            Log::info('🔍 [PROFILE ENRICHER] Iniciando enriquecimiento de perfil', [
                'user_id' => $userId,
            ]);

            // Obtener datos base del usuario
            $user = User::find($userId);
            if (! $user) {
                throw new \Exception("Usuario no encontrado: {$userId}");
            }

            // Calcular métricas de interacción
            $interactionMetrics = $this->calculateInteractionMetrics($userId);

            // Analizar preferencias de categorías con pesos inteligentes
            $categoryPreferences = $this->analyzeCategoryPreferences($userId);

            // Detectar patrones de comportamiento
            $behaviorPatterns = $this->detectBehaviorPatterns($userId);

            // Calcular afinidades de producto usando machine learning heurísticas
            $productAffinities = $this->calculateProductAffinities($userId);

            // Determinar segmento de usuario
            $userSegment = $this->determineUserSegment($userId, $interactionMetrics, $behaviorPatterns);

            // Calcular score de confianza del perfil
            $confidenceScore = $this->calculateProfileConfidence($interactionMetrics, $behaviorPatterns);

            $enrichedProfile = [
                'user_id' => $userId,
                'confidence_score' => $confidenceScore,
                'user_segment' => $userSegment,
                'interaction_metrics' => $interactionMetrics,
                'category_preferences' => $categoryPreferences,
                'behavior_patterns' => $behaviorPatterns,
                'product_affinities' => $productAffinities,
                'recommendation_weights' => $this->calculateRecommendationWeights($categoryPreferences, $behaviorPatterns),
                'updated_at' => now()->toISOString(),
            ];

            Log::info('✅ [PROFILE ENRICHER] Perfil enriquecido exitosamente', [
                'user_id' => $userId,
                'confidence_score' => $confidenceScore,
                'user_segment' => $userSegment,
                'categories_analyzed' => count($categoryPreferences),
                'behavior_patterns_detected' => count($behaviorPatterns),
            ]);

            return $enrichedProfile;

        } catch (\Exception $e) {
            Log::error('❌ [PROFILE ENRICHER] Error enriqueciendo perfil', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getBasicProfile($userId);
        }
    }

    /**
     * Calcula métricas avanzadas de interacción del usuario
     */
    private function calculateInteractionMetrics(int $userId): array
    {
        $interactions = UserInteraction::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(90)) // ✅ FIXED: created_at
            ->get();

        if ($interactions->isEmpty()) {
            return $this->getEmptyMetrics();
        }

        // Métricas básicas
        $totalInteractions = $interactions->count();
        $uniqueProducts = $interactions->where('item_id', '!=', null)->pluck('item_id')->unique()->count();
        $interactionTypes = $interactions->pluck('interaction_type')->unique()->count();

        // Calcular engagement score ponderado
        $weightedScore = 0;
        $interactionsByType = [];

        foreach ($interactions as $interaction) {
            $weight = UserInteraction::INTERACTION_WEIGHTS[$interaction->interaction_type] ?? 1.0;

            // Aplicar modificadores temporales (más reciente = más peso)
            $daysAgo = now()->diffInDays($interaction->created_at); // ✅ FIXED
            $timeDecay = max(0.1, 1 - ($daysAgo / 90)); // Decaimiento en 90 días

            $finalWeight = $weight * $timeDecay;
            $weightedScore += $finalWeight;

            $interactionsByType[$interaction->interaction_type] =
                ($interactionsByType[$interaction->interaction_type] ?? 0) + 1;
        }

        // Calcular velocidad de interacción (interacciones por día)
        $firstInteraction = $interactions->min('created_at'); // ✅ FIXED
        $daysSinceFirst = now()->diffInDays($firstInteraction);
        $interactionVelocity = $daysSinceFirst > 0 ? $totalInteractions / $daysSinceFirst : $totalInteractions;

        // Calcular diversidad de interacciones (índice Shannon)
        $diversityIndex = $this->calculateShannonDiversity($interactionsByType);

        // Patrones de sesión
        $sessionPatterns = $this->analyzeSessionPatterns($interactions);

        return [
            'total_interactions' => $totalInteractions,
            'unique_products' => $uniqueProducts,
            'interaction_types' => $interactionTypes,
            'weighted_engagement_score' => round($weightedScore, 2),
            'interaction_velocity' => round($interactionVelocity, 3),
            'diversity_index' => round($diversityIndex, 3),
            'interactions_by_type' => $interactionsByType,
            'session_patterns' => $sessionPatterns,
            'recency_score' => $this->calculateRecencyScore($interactions),
            'consistency_score' => $this->calculateConsistencyScore($interactions),
        ];
    }

    /**
     * Analiza preferencias de categorías con algoritmos heurísticos avanzados
     */
    private function analyzeCategoryPreferences(int $userId): array
    {
        // Obtener interacciones con productos y categorías
        $categoryInteractions = DB::table('user_interactions as ui')
            ->join('products as p', 'ui.item_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->where('ui.user_id', $userId)
            ->where('ui.created_at', '>=', now()->subDays(60)) // ✅ FIXED: created_at
            ->select([
                'c.id as category_id',
                'c.name as category_name',
                'ui.interaction_type',
                'ui.created_at', // ✅ FIXED
                'ui.metadata',
            ])
            ->get();

        if ($categoryInteractions->isEmpty()) {
            return [];
        }

        $categoryScores = [];
        $categoryMetrics = [];

        // Agrupar por categoría y calcular scores ponderados
        foreach ($categoryInteractions->groupBy('category_id') as $categoryId => $interactions) {
            $categoryName = $interactions->first()->category_name;

            $score = 0;
            $interactionCounts = [];
            $totalTime = 0;
            $purchaseCount = 0;

            foreach ($interactions as $interaction) {
                $weight = UserInteraction::INTERACTION_WEIGHTS[$interaction->interaction_type] ?? 1.0;

                // Bonus por tiempo de vista (si está disponible)
                if ($interaction->interaction_type === 'view_product' && $interaction->metadata) {
                    $metadata = json_decode($interaction->metadata, true);
                    if (isset($metadata['view_time'])) {
                        $viewTime = (int) $metadata['view_time'];
                        $totalTime += $viewTime;

                        // Bonus por vistas prolongadas
                        if ($viewTime >= 60) {
                            $weight *= 1.5;
                        } elseif ($viewTime >= 30) {
                            $weight *= 1.2;
                        }
                    }
                }

                // Bonus especial por compras
                if ($interaction->interaction_type === 'purchase') {
                    $purchaseCount++;
                    $weight *= 2.0; // Compras tienen peso doble
                }

                // Decaimiento temporal
                $daysAgo = now()->diffInDays($interaction->created_at); // ✅ FIXED
                $timeDecay = max(0.2, 1 - ($daysAgo / 60));

                $score += $weight * $timeDecay;
                $interactionCounts[$interaction->interaction_type] =
                    ($interactionCounts[$interaction->interaction_type] ?? 0) + 1;
            }

            $categoryScores[$categoryId] = $score;
            $categoryMetrics[$categoryId] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'preference_score' => round($score, 2),
                'total_interactions' => $interactions->count(),
                'interaction_types' => $interactionCounts,
                'average_view_time' => $totalTime > 0 ? round($totalTime / max(1, $interactionCounts['view_product'] ?? 1), 1) : 0,
                'purchase_count' => $purchaseCount,
                'engagement_level' => $this->categorizeEngagementLevel($score),
                'last_interaction' => $interactions->max('created_at'), // ✅ FIXED
            ];
        }

        // Normalizar scores (0-100)
        $maxScore = max($categoryScores) ?: 1;
        foreach ($categoryMetrics as $categoryId => &$metrics) {
            $metrics['normalized_score'] = round(($categoryScores[$categoryId] / $maxScore) * 100, 1);
        }

        // Ordenar por score descendente
        uasort($categoryMetrics, function ($a, $b) {
            return $b['preference_score'] <=> $a['preference_score'];
        });

        return array_values($categoryMetrics);
    }

    /**
     * Detecta patrones de comportamiento usando heurísticas avanzadas
     */
    private function detectBehaviorPatterns(int $userId): array
    {
        $interactions = UserInteraction::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(45)) // ✅ FIXED
            ->orderBy('created_at') // ✅ FIXED
            ->get();

        if ($interactions->isEmpty()) {
            return [];
        }

        $patterns = [];

        // Patrón 1: Navegador vs Comprador
        $patterns['shopping_behavior'] = $this->detectShoppingBehavior($interactions);

        // Patrón 2: Sensibilidad al precio
        $patterns['price_sensitivity'] = $this->detectPriceSensitivity($userId);

        // Patrón 3: Patrón temporal (cuándo está más activo)
        $patterns['temporal_patterns'] = $this->detectTemporalPatterns($interactions);

        // Patrón 4: Lealtad a marcas/sellers
        $patterns['brand_loyalty'] = $this->detectBrandLoyalty($userId);

        // Patrón 5: Influencia de descuentos
        $patterns['discount_sensitivity'] = $this->detectDiscountSensitivity($userId);

        // Patrón 6: Comportamiento de búsqueda
        $patterns['search_behavior'] = $this->detectSearchBehavior($userId);

        // Patrón 7: Tendencia de categorías (exploration vs exploitation)
        $patterns['category_exploration'] = $this->detectCategoryExploration($userId);

        return $patterns;
    }

    /**
     * Calcula afinidades de productos usando collaborative filtering simplificado
     */
    private function calculateProductAffinities(int $userId): array
    {
        // Obtener productos que ha interactuado el usuario
        $userProducts = UserInteraction::where('user_id', $userId)
            ->whereNotNull('item_id')
            ->where('created_at', '>=', now()->subDays(30)) // ✅ FIXED
            ->pluck('item_id')
            ->unique();

        if ($userProducts->isEmpty()) {
            return [];
        }

        // Encontrar usuarios similares (que han interactuado con productos similares)
        $similarUsers = DB::table('user_interactions')
            ->whereIn('item_id', $userProducts)
            ->where('user_id', '!=', $userId)
            ->where('created_at', '>=', now()->subDays(60)) // ✅ FIXED
            ->select('user_id', DB::raw('count(distinct item_id) as common_products'))
            ->groupBy('user_id')
            ->having('common_products', '>=', 2) // Al menos 2 productos en común
            ->orderBy('common_products', 'desc')
            ->limit(20) // Top 20 usuarios similares
            ->pluck('user_id');

        if ($similarUsers->isEmpty()) {
            return [];
        }

        // Obtener productos que han gustado a usuarios similares
        $recommendedProducts = DB::table('user_interactions as ui')
            ->join('products as p', 'ui.item_id', '=', 'p.id')
            ->whereIn('ui.user_id', $similarUsers)
            ->whereNotIn('ui.item_id', $userProducts) // Productos que el usuario no ha visto
            ->whereIn('ui.interaction_type', ['add_to_cart', 'purchase', 'add_to_favorites']) // Interacciones positivas
            ->where('ui.created_at', '>=', now()->subDays(30)) // ✅ FIXED
            ->where('p.status', 'active')
            ->where('p.published', true)
            ->select([
                'ui.item_id',
                'p.name',
                'p.price',
                'p.rating',
                'p.category_id',
                DB::raw('count(*) as affinity_score'),
                DB::raw('count(distinct ui.user_id) as similar_users_count'),
            ])
            ->groupBy('ui.item_id', 'p.name', 'p.price', 'p.rating', 'p.category_id')
            ->orderBy('affinity_score', 'desc')
            ->limit(50)
            ->get();

        return $recommendedProducts->map(function ($product) {
            return [
                'product_id' => $product->item_id,
                'product_name' => $product->name,
                'price' => $product->price,
                'rating' => $product->rating,
                'category_id' => $product->category_id,
                'affinity_score' => $product->affinity_score,
                'similar_users_count' => $product->similar_users_count,
                'confidence' => min(100, ($product->similar_users_count / 5) * 100), // Confianza basada en cantidad de usuarios
            ];
        })->toArray();
    }

    /**
     * Determina el segmento de usuario usando clustering heurístico
     */
    private function determineUserSegment(int $userId, array $metrics, array $patterns): array
    {
        $engagementScore = $metrics['weighted_engagement_score'] ?? 0;
        $diversityIndex = $metrics['diversity_index'] ?? 0;
        $totalInteractions = $metrics['total_interactions'] ?? 0;

        $shoppingBehavior = $patterns['shopping_behavior']['type'] ?? 'casual';
        $priceSensitivity = $patterns['price_sensitivity']['level'] ?? 'medium';

        // ✅ CORRECCIÓN: Si no hay interacciones, es un usuario nuevo
        if ($totalInteractions === 0) {
            return [
                'primary_segment' => 'new_user',
                'activity_level' => 'none',
                'sophistication' => 'unknown',
                'shopping_behavior' => 'unknown',
                'price_sensitivity' => 'unknown',
                'segment_confidence' => 0,
            ];
        }

        // Segmentación basada en múltiples dimensiones

        // Dimensión 1: Nivel de actividad
        if ($totalInteractions >= 50 && $engagementScore >= 100) {
            $activityLevel = 'high';
        } elseif ($totalInteractions >= 15 && $engagementScore >= 30) {
            $activityLevel = 'medium';
        } else {
            $activityLevel = 'low';
        }

        // Dimensión 2: Sofisticación del comprador
        if ($diversityIndex >= 0.7 && $engagementScore >= 50) {
            $sophistication = 'expert';
        } elseif ($diversityIndex >= 0.4) {
            $sophistication = 'intermediate';
        } else {
            $sophistication = 'novice';
        }

        // Determinar segmento principal
        $segment = $this->mapToSegment($activityLevel, $sophistication, $shoppingBehavior, $priceSensitivity);

        return [
            'primary_segment' => $segment,
            'activity_level' => $activityLevel,
            'sophistication' => $sophistication,
            'shopping_behavior' => $shoppingBehavior,
            'price_sensitivity' => $priceSensitivity,
            'segment_confidence' => $this->calculateSegmentConfidence($metrics, $patterns),
        ];
    }

    /**
     * Calcula pesos para diferentes estrategias de recomendación
     */
    private function calculateRecommendationWeights(array $categoryPreferences, array $behaviorPatterns): array
    {
        $weights = [
            'history_based' => 0.25,      // Basado en historial
            'category_based' => 0.20,     // Basado en categorías preferidas
            'collaborative' => 0.20,      // Filtrado colaborativo
            'content_based' => 0.15,      // Basado en contenido
            'trending' => 0.10,           // Productos en tendencia
            'demographic' => 0.10,         // Basado en demografía
        ];

        // Ajustar pesos según el perfil del usuario

        // Si tiene preferencias de categoría fuertes, aumentar peso de category_based
        if (! empty($categoryPreferences)) {
            $topCategoryScore = $categoryPreferences[0]['preference_score'] ?? 0;
            if ($topCategoryScore >= 50) {
                $weights['category_based'] += 0.10;
                $weights['demographic'] -= 0.05;
                $weights['trending'] -= 0.05;
            }
        }

        // Si es un comprador frecuente, aumentar collaborative filtering
        $shoppingBehavior = $behaviorPatterns['shopping_behavior']['type'] ?? 'casual';
        if ($shoppingBehavior === 'frequent_buyer') {
            $weights['collaborative'] += 0.10;
            $weights['content_based'] -= 0.05;
            $weights['demographic'] -= 0.05;
        }

        // Si es explorador de categorías, aumentar trending y content_based
        $categoryExploration = $behaviorPatterns['category_exploration']['type'] ?? 'focused';
        if ($categoryExploration === 'explorer') {
            $weights['trending'] += 0.05;
            $weights['content_based'] += 0.05;
            $weights['category_based'] -= 0.10;
        }

        // Normalizar pesos para que sumen 1.0
        $totalWeight = array_sum($weights);
        foreach ($weights as &$weight) {
            $weight = round($weight / $totalWeight, 3);
        }

        return $weights;
    }

    /**
     * Detecta comportamiento de compra (navegador vs comprador)
     */
    private function detectShoppingBehavior(object $interactions): array
    {
        $views = $interactions->where('interaction_type', 'view_product')->count();
        $cartAdditions = $interactions->where('interaction_type', 'add_to_cart')->count();
        $purchases = $interactions->where('interaction_type', 'purchase')->count();

        $viewToCartRatio = $views > 0 ? $cartAdditions / $views : 0;
        $cartToPurchaseRatio = $cartAdditions > 0 ? $purchases / $cartAdditions : 0;

        if ($purchases >= 3 && $cartToPurchaseRatio >= 0.7) {
            $type = 'frequent_buyer';
        } elseif ($purchases >= 1 && $viewToCartRatio >= 0.3) {
            $type = 'decisive_buyer';
        } elseif ($cartAdditions >= 5 && $cartToPurchaseRatio < 0.3) {
            $type = 'cart_abandoner';
        } elseif ($views >= 20 && $viewToCartRatio < 0.1) {
            $type = 'browser';
        } else {
            $type = 'casual';
        }

        return [
            'type' => $type,
            'view_to_cart_ratio' => round($viewToCartRatio, 3),
            'cart_to_purchase_ratio' => round($cartToPurchaseRatio, 3),
            'total_purchases' => $purchases,
            'confidence' => $this->calculateBehaviorConfidence($views, $cartAdditions, $purchases),
        ];
    }

    /**
     * Detecta sensibilidad al precio
     */
    private function detectPriceSensitivity(int $userId): array
    {
        $interactions = DB::table('user_interactions as ui')
            ->join('products as p', 'ui.item_id', '=', 'p.id')
            ->where('ui.user_id', $userId)
            ->whereIn('ui.interaction_type', ['view_product', 'add_to_cart', 'purchase'])
            ->where('ui.created_at', '>=', now()->subDays(60)) // ✅ FIXED
            ->select('p.price', 'p.discount_percentage', 'ui.interaction_type')
            ->get();

        if ($interactions->isEmpty()) {
            return ['level' => 'unknown', 'confidence' => 0];
        }

        $avgPrice = $interactions->avg('price');
        $discountedInteractions = $interactions->where('discount_percentage', '>', 0)->count();
        $totalInteractions = $interactions->count();

        $discountRatio = $totalInteractions > 0 ? $discountedInteractions / $totalInteractions : 0;

        if ($discountRatio >= 0.7) {
            $level = 'high';
        } elseif ($discountRatio >= 0.4) {
            $level = 'medium';
        } else {
            $level = 'low';
        }

        return [
            'level' => $level,
            'average_price_range' => $this->categorizePriceRange($avgPrice),
            'discount_interaction_ratio' => round($discountRatio, 3),
            'confidence' => min(100, $totalInteractions * 2),
        ];
    }

    /**
     * Detecta patrones temporales de actividad
     */
    private function detectTemporalPatterns(object $interactions): array
    {
        $hourlyActivity = [];
        $dailyActivity = [];

        foreach ($interactions as $interaction) {
            $hour = $interaction->created_at->hour; // ✅ FIXED
            $dayOfWeek = $interaction->created_at->dayOfWeek; // ✅ FIXED

            $hourlyActivity[$hour] = ($hourlyActivity[$hour] ?? 0) + 1;
            $dailyActivity[$dayOfWeek] = ($dailyActivity[$dayOfWeek] ?? 0) + 1;
        }

        $peakHour = $hourlyActivity ? array_keys($hourlyActivity, max($hourlyActivity))[0] : 12;
        $peakDay = $dailyActivity ? array_keys($dailyActivity, max($dailyActivity))[0] : 1;

        return [
            'peak_hour' => $peakHour,
            'peak_day' => $peakDay,
            'activity_pattern' => $this->classifyActivityPattern($hourlyActivity),
            'weekly_pattern' => $this->classifyWeeklyPattern($dailyActivity),
            'consistency_score' => $this->calculateTemporalConsistency($hourlyActivity, $dailyActivity),
        ];
    }

    /**
     * Helpers para cálculos específicos
     */
    private function calculateShannonDiversity(array $counts): float
    {
        $total = array_sum($counts);
        if ($total === 0) {
            return 0;
        }

        $diversity = 0;
        foreach ($counts as $count) {
            if ($count > 0) {
                $p = $count / $total;
                $diversity -= $p * log($p, 2);
            }
        }

        return $diversity;
    }

    private function calculateRecencyScore(object $interactions): float
    {
        $recentInteractions = $interactions->where('created_at', '>=', now()->subDays(7))->count(); // ✅ FIXED
        $totalInteractions = $interactions->count();

        return $totalInteractions > 0 ? ($recentInteractions / $totalInteractions) * 100 : 0;
    }

    private function calculateConsistencyScore(object $interactions): float
    {
        if ($interactions->count() < 7) {
            return 0;
        }

        $dailyActivity = [];
        foreach ($interactions as $interaction) {
            $date = $interaction->created_at->toDateString(); // ✅ FIXED
            $dailyActivity[$date] = ($dailyActivity[$date] ?? 0) + 1;
        }

        $activeDays = count($dailyActivity);
        $totalDays = now()->diffInDays($interactions->min('created_at')); // ✅ FIXED

        return $totalDays > 0 ? ($activeDays / $totalDays) * 100 : 0;
    }

    private function getEmptyMetrics(): array
    {
        return [
            'total_interactions' => 0,
            'unique_products' => 0,
            'interaction_types' => 0,
            'weighted_engagement_score' => 0,
            'interaction_velocity' => 0,
            'diversity_index' => 0,
            'interactions_by_type' => [],
            'session_patterns' => [],
            'recency_score' => 0,
            'consistency_score' => 0,
        ];
    }

    private function getBasicProfile(int $userId): array
    {
        return [
            'user_id' => $userId,
            'confidence_score' => 0,
            'user_segment' => ['primary_segment' => 'new_user'],
            'interaction_metrics' => $this->getEmptyMetrics(),
            'category_preferences' => [],
            'behavior_patterns' => [],
            'product_affinities' => [],
            'recommendation_weights' => [
                'demographic' => 0.4,
                'trending' => 0.3,
                'popular' => 0.3,
            ],
            'updated_at' => now()->toISOString(),
        ];
    }

    // Implementar métodos helper restantes...
    private function categorizeEngagementLevel(float $score): string
    {
        if ($score >= 100) {
            return 'very_high';
        }
        if ($score >= 50) {
            return 'high';
        }
        if ($score >= 20) {
            return 'medium';
        }

        return 'low';
    }

    private function analyzeSessionPatterns(object $interactions): array
    {
        // Implementación simplificada
        return [
            'avg_session_length' => 5,
            'session_frequency' => 'regular',
        ];
    }

    private function calculateProfileConfidence(array $metrics, array $patterns): float
    {
        $totalInteractions = $metrics['total_interactions'] ?? 0;
        $diversityIndex = $metrics['diversity_index'] ?? 0;
        $patternsDetected = count(array_filter($patterns));

        $confidence = min(100, ($totalInteractions * 2) + ($diversityIndex * 30) + ($patternsDetected * 10));

        return round($confidence, 1);
    }

    // Implementar métodos de detección de patrones adicionales según sea necesario...
    private function detectBrandLoyalty(int $userId): array
    {
        return ['type' => 'neutral'];
    }

    private function detectDiscountSensitivity(int $userId): array
    {
        return ['level' => 'medium'];
    }

    private function detectSearchBehavior(int $userId): array
    {
        return ['type' => 'exploratory'];
    }

    private function detectCategoryExploration(int $userId): array
    {
        return ['type' => 'focused'];
    }

    private function mapToSegment(string $activity, string $sophistication, string $shopping, string $price): string
    {
        return "{$activity}_{$sophistication}";
    }

    private function calculateSegmentConfidence(array $metrics, array $patterns): float
    {
        return 75.0;
    }

    private function calculateBehaviorConfidence(int $views, int $cart, int $purchases): float
    {
        return 80.0;
    }

    private function categorizePriceRange(float $price): string
    {
        if ($price >= 1000) {
            return 'premium';
        }
        if ($price >= 500) {
            return 'mid_range';
        }

        return 'budget';
    }

    private function classifyActivityPattern(array $hourly): string
    {
        return 'regular';
    }

    private function classifyWeeklyPattern(array $daily): string
    {
        return 'weekday_focused';
    }

    private function calculateTemporalConsistency(array $hourly, array $daily): float
    {
        return 70.0;
    }
}
