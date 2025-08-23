<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Product;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopularProductsStrategy implements StrategyInterface
{
    protected ProductFormatter $productFormatter;

    public function __construct(ProductFormatter $productFormatter)
    {
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'popular';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {

            // Construir query base con cálculo de ratings desde la tabla ratings
            $query = Product::query()
                ->leftJoin('ratings', 'products.id', '=', 'ratings.product_id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select([
                    'products.*',
                    'categories.name as category_name',
                    DB::raw('COALESCE(AVG(ratings.rating), products.rating, 0) as calculated_rating'),
                    DB::raw('COALESCE(COUNT(ratings.id), products.rating_count, 0) as calculated_rating_count'),
                    // Cálculo de score de popularidad ponderado (MySQL compatible)
                    DB::raw('(
                        (COALESCE(AVG(ratings.rating), products.rating, 0) * 0.3) +
                        (LEAST(products.view_count / 100.0, 10) * 0.25) +
                        (LEAST(products.sales_count / 10.0, 10) * 0.25) +
                        (LEAST(COALESCE(COUNT(ratings.id), products.rating_count, 0) / 5.0, 10) * 0.2)
                    ) as popularity_score'),
                ])
                ->where('products.status', 'active')
                ->where('products.published', true)
                ->where('products.stock', '>', 0)
                ->whereNotIn('products.id', $excludeProductIds)
                ->groupBy('products.id');

            // Aplicar filtros adicionales según el perfil del usuario
            $this->applyUserFilters($query, $userProfile);

            // Obtener productos ordenados por popularidad
            $products = $query
                ->orderBy('popularity_score', 'desc')
                ->orderBy('calculated_rating', 'desc')
                ->orderBy('products.view_count', 'desc')
                ->take($limit)
                ->get();

            if ($products->isEmpty()) {
                return [];
            }

            // Formatear productos con información de popularidad
            $result = [];
            foreach ($products as $product) {
                $formatted = $this->productFormatter->formatForApi($product);
                $formatted['recommendation_type'] = 'popular';
                $formatted['popularity_score'] = round($product->popularity_score, 2);
                $formatted['recommendation_reason'] = $this->generatePopularityReason($product);

                // Agregar información de categoría si no está presente
                if (! isset($formatted['category_name']) || empty($formatted['category_name'])) {
                    $formatted['category_name'] = $product->category_name;
                }

                $result[] = $formatted;
            }

            // Diversificar resultados para evitar monotonía
            $result = $this->diversifyResults($result, $limit);

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ [POPULAR STRATEGY] Error generando recomendaciones populares', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Aplica filtros basados en el perfil del usuario
     */
    private function applyUserFilters($query, ?UserProfile $userProfile): void
    {
        if (! $userProfile) {
            return;
        }

        $interests = $userProfile->getInterests();
        $demographics = $userProfile->getDemographics();

        // Filtro por interés en categorías (si el usuario tiene preferencias claras)
        if (! empty($interests)) {
            $topCategories = array_keys(array_slice($interests, 0, 3, true)); // Top 3 categorías

            // Aplicar boost a productos de categorías preferidas sin excluir otras
            $query->addSelect([
                DB::raw('CASE 
                    WHEN categories.name IN ("'.implode('", "', $topCategories).'") 
                    THEN (
                        (COALESCE(AVG(ratings.rating), products.rating, 0) * 0.3) +
                        (LEAST(products.view_count / 100.0, 10) * 0.25) +
                        (LEAST(products.sales_count / 10.0, 10) * 0.25) +
                        (LEAST(COALESCE(COUNT(ratings.id), products.rating_count, 0) / 5.0, 10) * 0.2)
                    ) * 1.2 
                    ELSE (
                        (COALESCE(AVG(ratings.rating), products.rating, 0) * 0.3) +
                        (LEAST(products.view_count / 100.0, 10) * 0.25) +
                        (LEAST(products.sales_count / 10.0, 10) * 0.25) +
                        (LEAST(COALESCE(COUNT(ratings.id), products.rating_count, 0) / 5.0, 10) * 0.2)
                    ) 
                END as boosted_popularity_score'),
            ]);
        }

        // Filtro demográfico suave (ajustar según edad si está disponible)
        if (isset($demographics['age'])) {
            $age = $demographics['age'];

            // Usuarios jóvenes (18-25): priorizar productos nuevos y tecnológicos
            if ($age >= 18 && $age <= 25) {
                $query->where(function ($q) {
                    $q->where('products.created_at', '>=', now()->subDays(90)) // Productos nuevos
                        ->orWhereIn('categories.name', ['Tecnología', 'Gaming', 'Móviles']); // Categorías tech
                });
            }
            // Usuarios maduros (40+): priorizar productos con muchas reviews y alta calidad
            elseif ($age >= 40) {
                $query->having('calculated_rating_count', '>=', 5) // Al menos 5 reviews
                    ->having('calculated_rating', '>=', 3.5); // Rating mínimo
            }
        }
    }

    /**
     * Diversifica los resultados para mostrar variedad de categorías
     */
    private function diversifyResults(array $products, int $limit): array
    {
        if (count($products) <= 5) {
            return $products; // No diversificar si hay pocos productos
        }

        $diversified = [];
        $categoriesUsed = [];
        $remainingProducts = $products;

        // Primera pasada: un producto por categoría
        foreach ($remainingProducts as $index => $product) {
            $categoryId = $product['category_id'];

            if (! in_array($categoryId, $categoriesUsed)) {
                $diversified[] = $product;
                $categoriesUsed[] = $categoryId;
                unset($remainingProducts[$index]);

                if (count($diversified) >= $limit) {
                    break;
                }
            }
        }

        // Segunda pasada: completar con productos restantes por popularidad
        $remainingProducts = array_values($remainingProducts);
        while (count($diversified) < $limit && ! empty($remainingProducts)) {
            $diversified[] = array_shift($remainingProducts);
        }

        return array_slice($diversified, 0, $limit);
    }

    /**
     * Genera razón de recomendación para productos populares
     */
    private function generatePopularityReason(object $product): string
    {
        $reasons = [];

        $rating = $product->calculated_rating ?? $product->rating ?? 0;
        $ratingCount = $product->calculated_rating_count ?? $product->rating_count ?? 0;
        $viewCount = $product->view_count ?? 0;
        $salesCount = $product->sales_count ?? 0;

        // Razón por rating alto
        if ($rating >= 4.5 && $ratingCount >= 10) {
            $reasons[] = "Excelente valoración ({$rating}/5 con {$ratingCount} opiniones)";
        } elseif ($rating >= 4.0 && $ratingCount >= 5) {
            $reasons[] = "Muy bien valorado ({$rating}/5)";
        }

        // Razón por popularidad
        if ($viewCount >= 1000) {
            $reasons[] = 'Muy popular entre usuarios';
        } elseif ($viewCount >= 500) {
            $reasons[] = 'Popular entre usuarios';
        }

        // Razón por ventas
        if ($salesCount >= 100) {
            $reasons[] = 'Producto muy vendido';
        } elseif ($salesCount >= 50) {
            $reasons[] = 'Producto con buenas ventas';
        }

        // Razón por descuento
        if ($product->discount_percentage > 0) {
            $reasons[] = "En oferta ({$product->discount_percentage}% descuento)";
        }

        // Razón por novedad
        if ($product->created_at && $product->created_at >= now()->subDays(30)) {
            $reasons[] = 'Producto nuevo';
        }

        return implode(' | ', $reasons) ?: 'Producto popular recomendado';
    }
}
