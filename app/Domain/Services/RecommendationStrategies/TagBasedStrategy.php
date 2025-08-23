<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Product;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\Log;

class TagBasedStrategy implements StrategyInterface
{
    protected ProductFormatter $productFormatter;

    public function __construct(ProductFormatter $productFormatter)
    {
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'tag';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {
            // Obtener productos vistos recientemente
            $viewedProductIds = UserInteraction::where('user_id', $userId)
                ->where('interaction_type', 'view_product')
                ->orderBy('interaction_time', 'desc')
                ->take(20)
                ->pluck('item_id')
                ->toArray();

            if (empty($viewedProductIds)) {
                return [];
            }

            // Obtener tags frecuentes de productos vistos
            $commonTags = [];
            $products = Product::whereIn('id', $viewedProductIds)->get();

            foreach ($products as $product) {
                if (is_array($product->tags)) {
                    foreach ($product->tags as $tag) {
                        if (! isset($commonTags[$tag])) {
                            $commonTags[$tag] = 0;
                        }
                        $commonTags[$tag]++;
                    }
                }
            }

            // Ordenar tags por frecuencia
            arsort($commonTags);
            $topTags = array_keys(array_slice($commonTags, 0, 5));

            if (empty($topTags)) {
                return [];
            }

            // Productos que contienen esos tags, excluyendo los ya vistos
            $result = [];
            $seenIds = $excludeProductIds;

            foreach ($topTags as $tag) {
                // Búsqueda por cada tag
                $tagProducts = Product::whereNotIn('id', array_merge($viewedProductIds, $seenIds))
                    ->whereJsonContains('tags', $tag)
                    ->where('published', true)
                    ->where('status', 'active')
                    ->where('stock', '>', 0)
                    ->orderBy('view_count', 'desc')
                    ->take(intval($limit / count($topTags)) + 1) // Distribución proporcional
                    ->get();

                foreach ($tagProducts as $product) {
                    $result[] = $this->productFormatter->formatForRecommendation($product, 'tag');
                    $seenIds[] = $product->id;

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in TagBasedStrategy: '.$e->getMessage());

            return [];
        }
    }
}
