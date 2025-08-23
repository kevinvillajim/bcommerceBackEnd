<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Product;
use App\Models\Rating;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HistoryBasedStrategy implements StrategyInterface
{
    protected ProductFormatter $productFormatter;

    public function __construct(ProductFormatter $productFormatter)
    {
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'history';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {

            // Obtener productos vistos recientemente con métricas de engagement
            $viewedProducts = UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'view_product')
                ->where('interaction_time', '>=', now()->subDays(60)) // Últimos 2 meses
                ->orderBy('interaction_time', 'desc')
                ->take(20) // Top 20 productos más recientes
                ->get();

            if ($viewedProducts->isEmpty()) {

                return [];
            }

            $viewedProductIds = $viewedProducts->pluck('item_id')->toArray();
            $viewedCategories = [];
            $viewedTags = [];
            $engagementScores = [];

            // Obtener categorías y tags de productos vistos
            $viewedProductDetails = Product::whereIn('id', $viewedProductIds)
                ->select('id', 'category_id', 'tags', 'price')
                ->get()
                ->keyBy('id');

            foreach ($viewedProducts as $interaction) {
                $productId = $interaction->item_id;
                $product = $viewedProductDetails->get($productId);

                if ($product) {
                    $viewedCategories[] = $product->category_id;

                    if ($product->tags) {
                        $viewedTags = array_merge($viewedTags, $product->tags);
                    }

                    // Calcular engagement score para este producto
                    $metadata = $interaction->metadata ?? [];
                    $viewTime = $metadata['view_time'] ?? 30;
                    $engagementScores[$productId] = $this->calculateEngagementScore($viewTime);
                }
            }

            $viewedCategories = array_unique($viewedCategories);
            $viewedTags = array_unique($viewedTags);

            Log::info('📊 [HISTORY STRATEGY] Perfil de usuario analizado', [
                'categories_count' => count($viewedCategories),
                'tags_count' => count($viewedTags),
                'avg_engagement' => count($engagementScores) > 0 ? round(array_sum($engagementScores) / count($engagementScores), 2) : 0,
            ]);

            // Construir query base con joins optimizados para ratings
            $query = Product::query()
                ->leftJoin('ratings', 'products.id', '=', 'ratings.product_id')
                ->select([
                    'products.*',
                    DB::raw('COALESCE(AVG(ratings.rating), products.rating, 0) as calculated_rating'),
                    DB::raw('COALESCE(COUNT(ratings.id), products.rating_count, 0) as calculated_rating_count'),
                ])
                ->where('products.published', true)
                ->where('products.status', 'active')
                ->where('products.stock', '>', 0)
                ->whereNotIn('products.id', array_merge($viewedProductIds, $excludeProductIds))
                ->groupBy('products.id');

            // Aplicar filtros de similitud con pesos
            $similarityConditions = [];

            // 70% peso: mismas categorías
            if (! empty($viewedCategories)) {
                $query->where(function ($q) use ($viewedCategories) {
                    $q->whereIn('products.category_id', $viewedCategories);
                });
                $similarityConditions[] = 'category_match';
            }

            // 20% peso: tags similares (si están disponibles)
            if (! empty($viewedTags)) {
                $query->where(function ($q) use ($viewedTags) {
                    foreach ($viewedTags as $tag) {
                        $q->orWhereJsonContains('products.tags', $tag);
                    }
                });
                $similarityConditions[] = 'tag_match';
            }

            // Ordenamiento inteligente basado en engagement del usuario
            $avgEngagement = count($engagementScores) > 0 ? array_sum($engagementScores) / count($engagementScores) : 2;

            if ($avgEngagement >= 3) {
                // Usuario con alto engagement: priorizar calidad (rating)
                $query->orderBy('calculated_rating', 'desc')
                    ->orderBy('calculated_rating_count', 'desc');
            } else {
                // Usuario con bajo engagement: priorizar popularidad
                $query->orderBy('products.view_count', 'desc')
                    ->orderBy('calculated_rating', 'desc');
            }

            $products = $query->take($limit)->get();

            // Load category relations separately to avoid join conflicts
            $productIds = $products->pluck('id')->toArray();
            $categories = DB::table('categories')->whereIn('id', $products->pluck('category_id'))->get()->keyBy('id');

            // Attach categories to products
            foreach ($products as $product) {
                if (isset($categories[$product->category_id])) {
                    $product->category = $categories[$product->category_id];
                }
            }

            Log::info('📋 [HISTORY STRATEGY] Productos similares encontrados', [
                'found_count' => $products->count(),
                'similarity_conditions' => $similarityConditions,
                'user_engagement_level' => $avgEngagement >= 3 ? 'high' : 'low',
            ]);

            if ($products->isEmpty()) {

                return [];
            }

            // Formatear productos con información de similitud
            $result = [];
            foreach ($products as $product) {
                $formatted = $this->productFormatter->formatForApi($product);
                $formatted['recommendation_type'] = 'history_based';
                $formatted['similarity_score'] = $this->calculateSimilarityScore(
                    $product, $viewedCategories, $viewedTags
                );
                $formatted['recommendation_reason'] = $this->generateRecommendationReason(
                    $product, $viewedCategories, $viewedTags
                );

                $result[] = $formatted;
            }

            Log::info('✅ [HISTORY STRATEGY] Recomendaciones generadas exitosamente', [
                'recommendations_count' => count($result),
                'avg_similarity_score' => count($result) > 0 ? round(array_sum(array_column($result, 'similarity_score')) / count($result), 2) : 0,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ [HISTORY STRATEGY] Error generando recomendaciones', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Calcula el score de engagement basado en tiempo de vista
     */
    private function calculateEngagementScore(int $viewTime): float
    {
        if ($viewTime >= 180) {
            return 5.0; // Muy alto engagement
        } elseif ($viewTime >= 120) {
            return 4.0; // Alto engagement
        } elseif ($viewTime >= 60) {
            return 3.0; // Engagement medio
        } elseif ($viewTime >= 30) {
            return 2.0; // Engagement bajo
        } else {
            return 1.0; // Muy bajo engagement
        }
    }

    /**
     * Calcula score de similitud entre un producto y el perfil del usuario
     */
    private function calculateSimilarityScore(Product $product, array $viewedCategories, array $viewedTags): float
    {
        $score = 0;

        // Similitud de categoría (70% del peso)
        if (in_array($product->category_id, $viewedCategories)) {
            $score += 0.7;
        }

        // Similitud de tags (20% del peso)
        if ($product->tags && ! empty($viewedTags)) {
            $commonTags = array_intersect($product->tags, $viewedTags);
            $tagSimilarity = count($commonTags) / max(count($viewedTags), 1);
            $score += $tagSimilarity * 0.2;
        }

        // Bonus por calidad del producto (10% del peso)
        $qualityBonus = ($product->calculated_rating ?? $product->rating ?? 0) / 5.0;
        $score += $qualityBonus * 0.1;

        return round($score, 3);
    }

    /**
     * Genera razón de recomendación personalizada
     */
    private function generateRecommendationReason(Product $product, array $viewedCategories, array $viewedTags): string
    {
        $reasons = [];

        if (in_array($product->category_id, $viewedCategories)) {
            $categoryName = $product->category->name ?? 'esta categoría';
            $reasons[] = "Basado en tu interés en {$categoryName}";
        }

        if ($product->tags && ! empty($viewedTags)) {
            $commonTags = array_intersect($product->tags, $viewedTags);
            if (! empty($commonTags)) {
                $tag = $commonTags[0];
                $reasons[] = "Porque te interesan productos relacionados con {$tag}";
            }
        }

        $rating = $product->calculated_rating ?? $product->rating ?? 0;
        if ($rating >= 4.0) {
            $reasons[] = "Producto muy bien valorado ({$rating}/5)";
        }

        return implode(' | ', $reasons) ?: 'Producto recomendado para ti';
    }
}
