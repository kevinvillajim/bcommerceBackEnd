<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class CategoryBasedStrategy implements StrategyInterface
{
    protected UserProfileRepositoryInterface $userProfileRepository;

    protected ProductFormatter $productFormatter;

    public function __construct(
        UserProfileRepositoryInterface $userProfileRepository,
        ProductFormatter $productFormatter
    ) {
        $this->userProfileRepository = $userProfileRepository;
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'category';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {
            // Si no hay perfil o no hay categorías disponibles, devolver vacío
            if (! $userProfile) {
                return [];
            }

            $categoryPreferences = $this->userProfileRepository->getCategoryPreferences($userId);
            if (empty($categoryPreferences)) {
                return [];
            }

            $topCategories = array_slice($categoryPreferences, 0, 3, true);
            $result = [];

            // Distribuir el límite entre las categorías
            $limitPerCategory = max(1, intval($limit / count($topCategories)));

            foreach ($topCategories as $categoryId => $weight) {
                $categoryProducts = Product::where('category_id', $categoryId)
                    ->whereNotIn('id', $excludeProductIds)
                    ->where('published', true)
                    ->where('status', 'active')
                    ->orderBy('rating', 'desc')
                    ->limit($limitPerCategory)
                    ->get();

                foreach ($categoryProducts as $product) {
                    $result[] = $this->productFormatter->formatForRecommendation($product, 'category');

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in CategoryBasedStrategy: '.$e->getMessage());

            return [];
        }
    }
}
