<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\FavoriteRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class FavoritesBasedStrategy implements StrategyInterface
{
    /**
     * @var FavoriteRepositoryInterface
     */
    private $favoriteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductFormatter
     */
    private $productFormatter;

    /**
     * Constructor
     */
    public function __construct(
        FavoriteRepositoryInterface $favoriteRepository,
        ProductRepositoryInterface $productRepository,
        ProductFormatter $productFormatter
    ) {
        $this->favoriteRepository = $favoriteRepository;
        $this->productRepository = $productRepository;
        $this->productFormatter = $productFormatter;
    }

    /**
     * Get strategy name
     */
    public function getName(): string
    {
        return 'favorites_based';
    }

    /**
     * Generate recommendations based on user favorites
     */
    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile = null,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {
            // Get user's favorites with associated products
            $favorites = $this->favoriteRepository->getUserFavorites($userId, 100, 0);

            if (empty($favorites)) {
                return [];
            }

            // Extract product categories and tags from favorites
            $favoriteCategories = [];
            $favoriteTags = [];
            $favoriteProductIds = [];

            foreach ($favorites as $favorite) {
                if (isset($favorite['product'])) {
                    $productData = $favorite['product'];
                    $favoriteProductIds[] = $productData['id'];

                    // Count category occurrences
                    if (isset($productData['category_id'])) {
                        $categoryId = $productData['category_id'];
                        $favoriteCategories[$categoryId] = ($favoriteCategories[$categoryId] ?? 0) + 1;
                    }

                    // Count tag occurrences
                    if (isset($productData['tags']) && is_array($productData['tags'])) {
                        foreach ($productData['tags'] as $tag) {
                            $favoriteTags[$tag] = ($favoriteTags[$tag] ?? 0) + 1;
                        }
                    }
                }
            }

            // Sort categories and tags by occurrence count
            arsort($favoriteCategories);
            arsort($favoriteTags);

            // Get top categories and tags
            $topCategories = array_keys(array_slice($favoriteCategories, 0, 3));
            $topTags = array_keys(array_slice($favoriteTags, 0, 5));

            // Make sure we exclude the products that are already favorited
            $excludeProductIds = array_merge($excludeProductIds, $favoriteProductIds);

            $result = [];

            // Get similar products by categories (60% of recommendations)
            $categoryLimit = min(intval($limit * 0.6), count($topCategories) * 3);
            if (! empty($topCategories) && $categoryLimit > 0) {
                $categoryProducts = Product::whereIn('category_id', $topCategories)
                    ->whereNotIn('id', $excludeProductIds)
                    ->where('published', true)
                    ->where('status', 'active')
                    ->where('stock', '>', 0)
                    ->orderBy('rating', 'desc')
                    ->orderBy('view_count', 'desc')
                    ->take($categoryLimit)
                    ->get();

                foreach ($categoryProducts as $product) {
                    $result[] = $this->productFormatter->formatForRecommendation($product, 'favorites_based');
                    $excludeProductIds[] = $product->id;

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }

            // Get similar products by tags (40% of recommendations)
            if (! empty($topTags)) {
                $tagLimit = $limit - count($result);

                foreach ($topTags as $tag) {
                    $tagProducts = Product::whereNotIn('id', $excludeProductIds)
                        ->whereJsonContains('tags', $tag)
                        ->where('published', true)
                        ->where('status', 'active')
                        ->where('stock', '>', 0)
                        ->orderBy('rating', 'desc')
                        ->take(intval($tagLimit / count($topTags)) + 1)
                        ->get();

                    foreach ($tagProducts as $product) {
                        $result[] = $this->productFormatter->formatForRecommendation($product, 'favorites_based');
                        $excludeProductIds[] = $product->id;

                        if (count($result) >= $limit) {
                            return $result;
                        }
                    }
                }
            }

            // If we still need more recommendations, get related products
            if (count($result) < $limit && ! empty($favoriteProductIds)) {
                // Get products that users who favorited these products also favorited
                $relatedLimit = $limit - count($result);

                $relatedProducts = Product::whereNotIn('id', $excludeProductIds)
                    ->where('published', true)
                    ->where('status', 'active')
                    ->where('stock', '>', 0)
                    ->whereHas('favorites', function ($query) use ($favoriteProductIds) {
                        $query->whereHas('user.favorites', function ($q) use ($favoriteProductIds) {
                            $q->whereIn('product_id', $favoriteProductIds);
                        });
                    })
                    ->orderBy('rating', 'desc')
                    ->take($relatedLimit)
                    ->get();

                foreach ($relatedProducts as $product) {
                    $result[] = $this->productFormatter->formatForRecommendation($product, 'favorites_based');

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in FavoritesBasedStrategy: '.$e->getMessage());

            return [];
        }
    }
}
