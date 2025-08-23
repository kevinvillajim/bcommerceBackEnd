<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class InterestBasedStrategy implements StrategyInterface
{
    protected ProductFormatter $productFormatter;

    public function __construct(ProductFormatter $productFormatter)
    {
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'interest';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {
            if (! $userProfile) {
                return [];
            }

            $interests = $userProfile->getInterests();
            if (empty($interests)) {
                return [];
            }

            $topInterests = array_slice($interests, 0, 3, true);
            $result = [];

            // Distribuir el límite entre los intereses
            $limitPerInterest = max(1, intval($limit / count($topInterests)));

            foreach (array_keys($topInterests) as $interest) {
                // Buscar categorías relacionadas con el interés
                $categories = Category::where('name', 'like', "%{$interest}%")->get();

                if ($categories->isNotEmpty()) {
                    $interestProducts = Product::whereIn('category_id', $categories->pluck('id'))
                        ->whereNotIn('id', $excludeProductIds)
                        ->where('published', true)
                        ->where('status', 'active')
                        ->orderBy('rating', 'desc')
                        ->limit($limitPerInterest)
                        ->get();
                } else {
                    // Buscar directamente en productos
                    $interestProducts = Product::where(function ($query) use ($interest) {
                        $query->where('name', 'like', "%{$interest}%")
                            ->orWhere('description', 'like', "%{$interest}%")
                            ->orWhereJsonContains('tags', $interest);
                    })
                        ->whereNotIn('id', $excludeProductIds)
                        ->where('published', true)
                        ->where('status', 'active')
                        ->orderBy('rating', 'desc')
                        ->limit($limitPerInterest)
                        ->get();
                }

                foreach ($interestProducts as $product) {
                    $result[] = $this->productFormatter->formatForRecommendation($product, 'interest');
                    $excludeProductIds[] = $product->id;

                    if (count($result) >= $limit) {
                        return $result;
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in InterestBasedStrategy: '.$e->getMessage());

            return [];
        }
    }
}
