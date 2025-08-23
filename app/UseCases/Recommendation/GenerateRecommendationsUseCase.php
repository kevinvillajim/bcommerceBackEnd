<?php

namespace App\UseCases\Recommendation;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class GenerateRecommendationsUseCase
{
    private RecommendationEngineInterface $recommendationEngine;

    public function __construct(RecommendationEngineInterface $recommendationEngine)
    {
        $this->recommendationEngine = $recommendationEngine;
    }

    /**
     * Genera recomendaciones REALES para un usuario basadas en sus gustos
     *
     * @param  int  $userId  ID del usuario
     * @param  int  $limit  Número de productos a recomendar
     */
    public function execute(int $userId, int $limit = 5): array
    {
        try {
            // Obtener ProductFormatter desde el container
            $productFormatter = app(ProductFormatter::class);

            // Intentar obtener recomendaciones del motor inteligente
            $recommendations = $this->recommendationEngine->generateRecommendations($userId, $limit);

            // Si hay recomendaciones del motor, verificar si son productos reales o IDs
            if (! empty($recommendations)) {
                // Verificar si las recomendaciones ya están formateadas o son IDs
                $firstItem = $recommendations[0] ?? null;

                // ✅ VERIFICAR QUE TENGA TODOS LOS CAMPOS NECESARIOS
                if (is_array($firstItem) && isset($firstItem['id']) && isset($firstItem['name']) &&
                    isset($firstItem['price']) && isset($firstItem['rating']) && isset($firstItem['rating_count']) &&
                    isset($firstItem['main_image']) && isset($firstItem['images']) && isset($firstItem['category_name'])) {
                    // Ya están formateadas COMPLETAMENTE, solo mezclar
                    shuffle($recommendations);

                    return array_slice($recommendations, 0, $limit);
                }

                // Son IDs o están incompletas, obtener productos reales OPTIMIZADO
                $productIds = [];
                foreach ($recommendations as $rec) {
                    if (is_array($rec) && isset($rec['id'])) {
                        $productIds[] = $rec['id'];
                    } elseif (is_numeric($rec)) {
                        $productIds[] = $rec;
                    }
                }

                if (! empty($productIds)) {
                    // ✅ CONSULTA OPTIMIZADA con todas las relaciones y campos necesarios

                    $realProducts = Product::whereIn('id', $productIds)
                        ->where('status', 'active')
                        ->where('published', true)
                        ->where('stock', '>', 0)
                        ->with('category') // ✅ Cargar relación de categoría para evitar N+1
                        ->select([
                            'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                            'discount_percentage', 'images', 'category_id', 'stock',
                            'featured', 'status', 'tags', 'seller_id', 'user_id',
                            'created_at', 'updated_at', 'published',
                        ])
                        ->get();

                    if ($realProducts->isNotEmpty()) {
                        $formattedProducts = [];
                        foreach ($realProducts as $index => $product) {
                            try {
                                $formatted = $productFormatter->formatForApi($product);
                                $formatted['recommendation_type'] = 'intelligent';

                                // ✅ VALIDACIÓN CRÍTICA: Asegurar campos esenciales
                                if (! isset($formatted['price']) || ! is_numeric($formatted['price'])) {
                                    $formatted['price'] = (float) ($product->price ?? 0);
                                }

                                if (! isset($formatted['rating']) || ! is_numeric($formatted['rating'])) {
                                    $formatted['rating'] = (float) ($product->rating ?? 0);
                                }

                                if (! isset($formatted['rating_count']) || ! is_numeric($formatted['rating_count'])) {
                                    $formatted['rating_count'] = (int) ($product->rating_count ?? 0);
                                }

                                if (! isset($formatted['images']) || ! is_array($formatted['images'])) {
                                    $formatted['images'] = [];
                                }

                                if (! isset($formatted['category_name'])) {
                                    $formatted['category_name'] = $product->category->name ?? null;
                                }

                                $formattedProducts[] = $formatted;

                            } catch (\Exception $e) {
                                Log::error("❌ [FORMAT_ERROR] Error formateando producto {$product->id}: ".$e->getMessage());

                                // ✅ FALLBACK MANUAL: Crear producto básico si el formatter falla
                                $formattedProducts[] = [
                                    'id' => (int) $product->id,
                                    'name' => (string) ($product->name ?? 'Producto sin nombre'),
                                    'slug' => (string) ($product->slug ?? ''),
                                    'price' => (float) ($product->price ?? 0),
                                    'final_price' => (float) ($product->price ?? 0),
                                    'rating' => (float) ($product->rating ?? 0),
                                    'rating_count' => (int) ($product->rating_count ?? 0),
                                    'discount_percentage' => (float) ($product->discount_percentage ?? 0),
                                    'main_image' => null,
                                    'images' => [],
                                    'category_id' => (int) ($product->category_id ?? 0),
                                    'category_name' => $product->category->name ?? null,
                                    'stock' => (int) ($product->stock ?? 0),
                                    'is_in_stock' => ($product->stock ?? 0) > 0,
                                    'featured' => (bool) ($product->featured ?? false),
                                    'published' => (bool) ($product->published ?? false),
                                    'status' => (string) ($product->status ?? 'inactive'),
                                    'tags' => $product->tags,
                                    'seller_id' => (int) ($product->seller_id ?? $product->user_id ?? 0),
                                    'created_at' => $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : null,
                                    'recommendation_type' => 'intelligent',
                                ];
                            }
                        }

                        shuffle($formattedProducts);

                        return array_slice($formattedProducts, 0, $limit);
                    }
                }
            }

            // Fallback: productos basados en patrones del usuario

            return $this->getIntelligentFallback($userId, $limit, $productFormatter);

        } catch (\Exception $e) {
            Log::error('❌ [EXCEPTION] GenerateRecommendationsUseCase: Error generando recomendaciones', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'limit' => $limit,
                'trace' => $e->getTraceAsString(),
            ]);

            // Error fallback: productos populares REALES
            $productFormatter = app(ProductFormatter::class);

            return $this->getIntelligentFallback($userId, $limit, $productFormatter);
        }
    }

    /**
     * Fallback inteligente: productos REALES basados en popularidad y patrones
     */
    private function getIntelligentFallback(int $userId, int $limit, ProductFormatter $productFormatter): array
    {
        try {

            // Estrategia de fallback inteligente optimizada:
            // 1. Productos más populares (mejor rating + más vendidos)
            // 2. Productos con descuentos atractivos
            // 3. Productos nuevos y tendencia
            // 4. Productos de calidad disponibles

            $fallbackProducts = collect();
            $usedIds = [];

            // 40% - Productos más vendidos y mejor valorados
            $popularProducts = Product::where('status', 'active')
                ->where('published', true)
                ->where('stock', '>', 0)
                ->where('rating', '>=', 3.5) // Productos decentemente valorados
                ->with('category') // ✅ EAGER LOADING para evitar N+1
                ->select([
                    'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                    'discount_percentage', 'images', 'category_id', 'stock',
                    'featured', 'status', 'tags', 'seller_id', 'user_id',
                    'sales_count', 'view_count', 'created_at', 'updated_at', 'published',
                ])
                ->orderByRaw('(sales_count + view_count) * rating DESC') // Combinar popularidad y rating
                ->inRandomOrder()
                ->take(ceil($limit * 0.4))
                ->get();

            foreach ($popularProducts as $product) {
                try {
                    $formatted = $productFormatter->formatForApi($product);
                    $formatted['recommendation_type'] = 'intelligent';

                    // ✅ VALIDACIÓN ESTRICTA: Verificar que tenga todos los campos críticos
                    if ($this->validateProductFormat($formatted)) {
                        $fallbackProducts->push($formatted);
                        $usedIds[] = $product->id;
                    }
                } catch (\Exception $e) {
                    Log::error('❌ [FALLBACK] Error formateando producto popular: '.$e->getMessage());
                }
            }

            // 30% - Productos con descuentos atractivos
            if ($fallbackProducts->count() < $limit) {
                $discountedProducts = Product::where('status', 'active')
                    ->where('published', true)
                    ->where('stock', '>', 0)
                    ->where('discount_percentage', '>', 5) // Descuentos del 5% o más
                    ->whereNotIn('id', $usedIds)
                    ->with('category') // ✅ EAGER LOADING
                    ->select([
                        'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                        'discount_percentage', 'images', 'category_id', 'stock',
                        'featured', 'status', 'tags', 'seller_id', 'user_id',
                        'created_at', 'updated_at', 'published',
                    ])
                    ->orderBy('discount_percentage', 'desc')
                    ->inRandomOrder()
                    ->take(ceil($limit * 0.3))
                    ->get();

                foreach ($discountedProducts as $product) {
                    if ($fallbackProducts->count() >= $limit) {
                        break;
                    }

                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';

                        if ($this->validateProductFormat($formatted)) {
                            $fallbackProducts->push($formatted);
                            $usedIds[] = $product->id;
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ [FALLBACK] Error formateando producto con descuento: '.$e->getMessage());
                    }
                }
            }

            // 30% - Productos nuevos y en tendencia
            if ($fallbackProducts->count() < $limit) {
                $trendingProducts = Product::where('status', 'active')
                    ->where('published', true)
                    ->where('stock', '>', 0)
                    ->where('created_at', '>=', now()->subDays(60)) // Productos de últimos 2 meses
                    ->whereNotIn('id', $usedIds)
                    ->with('category') // ✅ EAGER LOADING
                    ->select([
                        'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                        'discount_percentage', 'images', 'category_id', 'stock',
                        'featured', 'status', 'tags', 'seller_id', 'user_id',
                        'view_count', 'created_at', 'updated_at', 'published',
                    ])
                    ->orderBy('view_count', 'desc')
                    ->inRandomOrder()
                    ->take($limit - $fallbackProducts->count())
                    ->get();

                foreach ($trendingProducts as $product) {
                    if ($fallbackProducts->count() >= $limit) {
                        break;
                    }

                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';

                        if ($this->validateProductFormat($formatted)) {
                            $fallbackProducts->push($formatted);
                            $usedIds[] = $product->id;
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ [FALLBACK] Error formateando producto trending: '.$e->getMessage());
                    }
                }
            }

            // Si aún no tenemos suficientes, completar con cualquier producto activo de calidad
            if ($fallbackProducts->count() < $limit) {
                $remainingProducts = Product::where('status', 'active')
                    ->where('published', true)
                    ->where('stock', '>', 0)
                    ->whereNotIn('id', $usedIds)
                    ->with('category') // ✅ EAGER LOADING
                    ->select([
                        'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                        'discount_percentage', 'images', 'category_id', 'stock',
                        'featured', 'status', 'tags', 'seller_id', 'user_id',
                        'created_at', 'updated_at', 'published',
                    ])
                    ->inRandomOrder()
                    ->take($limit - $fallbackProducts->count())
                    ->get();

                foreach ($remainingProducts as $product) {
                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';

                        if ($this->validateProductFormat($formatted)) {
                            $fallbackProducts->push($formatted);
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ [FALLBACK] Error formateando producto restante: '.$e->getMessage());
                    }
                }
            }

            // Mezclar y tomar exactamente el límite solicitado
            $finalProducts = $fallbackProducts->shuffle()->take($limit)->values()->toArray();

            return $finalProducts;

        } catch (\Exception $e) {
            Log::error('❌ Error en fallback inteligente: '.$e->getMessage());

            // Último recurso: productos básicos REALES OPTIMIZADOS
            try {
                $basicProducts = Product::where('status', 'active')
                    ->where('published', true)
                    ->where('stock', '>', 0)
                    ->with('category') // ✅ EAGER LOADING
                    ->select([
                        'id', 'name', 'slug', 'price', 'rating', 'rating_count',
                        'discount_percentage', 'images', 'category_id', 'stock',
                        'featured', 'status', 'tags', 'seller_id', 'user_id',
                        'created_at', 'updated_at', 'published',
                    ])
                    ->inRandomOrder()
                    ->take($limit)
                    ->get();

                $result = [];
                foreach ($basicProducts as $product) {
                    try {
                        $formatted = $productFormatter->formatForApi($product);
                        $formatted['recommendation_type'] = 'intelligent';

                        if ($this->validateProductFormat($formatted)) {
                            $result[] = $formatted;
                        }
                    } catch (\Exception $formatError) {
                        Log::error('❌ Error formateando producto básico: '.$formatError->getMessage());
                    }
                }

                return $result;

            } catch (\Exception $basicError) {
                Log::error('❌ Error crítico en último recurso: '.$basicError->getMessage());

                return [];
            }
        }
    }

    /**
     * Valida que un producto formateado tenga todos los campos necesarios
     */
    private function validateProductFormat(array $product): bool
    {
        // Campos absolutamente críticos
        $criticalFields = ['id', 'name'];

        foreach ($criticalFields as $field) {
            if (! isset($product[$field]) || empty($product[$field])) {
                return false;
            }
        }

        // Campos numéricos - validar que existan y sean numéricos, pero permitir 0
        $numericFields = ['price', 'rating', 'rating_count'];
        foreach ($numericFields as $field) {
            if (isset($product[$field]) && ! is_numeric($product[$field])) {
                return false;
            }
        }

        // Arrays - validar que sean arrays si existen
        if (isset($product['images']) && ! is_array($product['images'])) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene los campos faltantes de un producto
     */
    private function getMissingFields(array $product): array
    {
        $requiredFields = ['id', 'name', 'price', 'rating', 'rating_count', 'images', 'main_image', 'category_name'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (! isset($product[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }
}
