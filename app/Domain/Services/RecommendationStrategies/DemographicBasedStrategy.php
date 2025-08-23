<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Services\DemographicProfileGenerator;
use App\Domain\ValueObjects\UserProfile;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class DemographicBasedStrategy implements StrategyInterface
{
    protected DemographicProfileGenerator $demographicGenerator;

    protected ProductFormatter $productFormatter;

    public function __construct(
        DemographicProfileGenerator $demographicGenerator,
        ProductFormatter $productFormatter
    ) {
        $this->demographicGenerator = $demographicGenerator;
        $this->productFormatter = $productFormatter;
    }

    public function getName(): string
    {
        return 'demographic';
    }

    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array {
        try {
            if (! $userProfile || empty($userProfile->getDemographics())) {
                return [];
            }

            $interests = $this->demographicGenerator->generate($userProfile->getDemographics());
            $topInterests = array_keys(array_slice($interests, 0, 3, true));

            $products = collect();

            foreach ($topInterests as $interest) {
                // Buscar categorías relacionadas con el interés
                $categories = Category::where('name', 'like', "%{$interest}%")->get();

                if ($categories->isNotEmpty()) {
                    $categoryProducts = Product::whereIn('category_id', $categories->pluck('id'))
                        ->whereNotIn('id', $excludeProductIds)
                        ->where('published', true)
                        ->where('status', 'active')
                        ->orderBy('rating', 'desc')
                        ->limit(5)
                        ->get();

                    $products = $products->merge($categoryProducts);
                } else {
                    // Buscar directamente en productos
                    $interestProducts = Product::where('name', 'like', "%{$interest}%")
                        ->orWhere('description', 'like', "%{$interest}%")
                        ->orWhereJsonContains('tags', $interest)
                        ->whereNotIn('id', $excludeProductIds)
                        ->where('published', true)
                        ->where('status', 'active')
                        ->orderBy('rating', 'desc')
                        ->limit(5)
                        ->get();

                    $products = $products->merge($interestProducts);
                }
            }

            $result = [];
            foreach ($products->take($limit) as $product) {
                $result[] = $this->productFormatter->formatForRecommendation($product, 'demographic');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in DemographicBasedStrategy: '.$e->getMessage());

            return [];
        }
    }
}
