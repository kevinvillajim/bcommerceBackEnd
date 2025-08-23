<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\UserInteractionEntity;
use App\Domain\Formatters\ProductFormatter;
use App\Domain\Formatters\UserProfileFormatter;
use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use App\Domain\Services\DemographicProfileGenerator;
use App\Domain\Services\ProfileCompletenessCalculator;
use App\Domain\Services\RecommendationStrategies\StrategyInterface;
use App\Domain\Services\UserProfileEnricher;
use App\Domain\ValueObjects\UserProfile;
use Illuminate\Support\Facades\Log;

class RecommendationService implements RecommendationEngineInterface
{
    protected UserProfileRepositoryInterface $userProfileRepository;

    protected ProductRepositoryInterface $productRepository;

    protected UserProfileEnricher $userProfileEnricher;

    protected DemographicProfileGenerator $demographicGenerator;

    protected ProfileCompletenessCalculator $completenessCalculator;

    protected ProductFormatter $productFormatter;

    protected UserProfileFormatter $profileFormatter;

    /** @var StrategyInterface[] */
    protected array $strategies = [];

    public function __construct(
        UserProfileRepositoryInterface $userProfileRepository,
        ProductRepositoryInterface $productRepository,
        UserProfileEnricher $userProfileEnricher,
        DemographicProfileGenerator $demographicGenerator,
        ProfileCompletenessCalculator $completenessCalculator,
        ProductFormatter $productFormatter,
        UserProfileFormatter $profileFormatter,
        array $strategies = []
    ) {
        $this->userProfileRepository = $userProfileRepository;
        $this->productRepository = $productRepository;
        $this->userProfileEnricher = $userProfileEnricher;
        $this->demographicGenerator = $demographicGenerator;
        $this->completenessCalculator = $completenessCalculator;
        $this->productFormatter = $productFormatter;
        $this->profileFormatter = $profileFormatter;
        $this->strategies = $strategies;
    }

    /**
     * Agrega una estrategia de recomendación
     */
    public function addStrategy(StrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    /**
     * {@inheritdoc}
     */
    public function generateRecommendations(int $userId, int $limit = 10): array
    {
        try {
            // Get user profile
            $userProfile = $this->getUserProfile($userId);

            // Get already viewed products to exclude
            $viewedProductIds = $this->userProfileRepository->getViewedProductIds($userId);

            // Prepare recommendations container
            $recommendedProducts = [];
            $productIds = [];

            // Check if we have enough user data
            $hasProfileData = ! empty($userProfile->getInterests()) ||
                ! empty($userProfile->getViewedProducts());

            // Set initial strategy based on user profile
            $initialStrategy = 'popular';
            if (! $hasProfileData) {
                // If no profile data, use demographic or popular
                $initialStrategy = 'demographic';
            } elseif (! empty($userProfile->getViewedProducts())) {
                // If user has viewed products, start with history
                $initialStrategy = 'history';
            } elseif (! empty($userProfile->getInterests())) {
                // If user has interests but no views, start with interest
                $initialStrategy = 'interest';
            }

            // Define strategy weights (total should be 100%)
            $strategyWeights = [
                'history' => 0.30,    // 30% for history-based
                'category' => 0.15,   // 15% for category-based
                'tag' => 0.15,        // 15% for tag-based
                'favorites' => 0.20,  // 20% for favorites-based
                'demographic' => 0.10, // 10% for demographic
                'popular' => 0.10,     // 10% for popular products
            ];

            // Adjust weights based on initial strategy
            $strategyWeights[$initialStrategy] += 0.15;

            // Apply weighted distribution of recommendations
            foreach ($strategyWeights as $strategyName => $weight) {
                $strategyLimit = max(1, round($limit * $weight));

                if (count($recommendedProducts) >= $limit) {
                    break;
                }

                // Find strategy
                $strategy = null;
                foreach ($this->strategies as $s) {
                    if ($s->getName() === $strategyName) {
                        $strategy = $s;
                        break;
                    }
                }

                if ($strategy) {
                    $remaining = $limit - count($recommendedProducts);
                    $recommendations = $strategy->getRecommendations(
                        $userId,
                        $userProfile,
                        array_merge($viewedProductIds, $productIds),
                        min($strategyLimit, $remaining)
                    );

                    // Add to recommendations and track IDs
                    foreach ($recommendations as $product) {
                        if (! in_array($product['id'], $productIds)) {
                            $recommendedProducts[] = $product;
                            $productIds[] = $product['id'];

                            if (count($recommendedProducts) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            }

            // Fill remaining slots with popular products if needed
            if (count($recommendedProducts) < $limit) {
                foreach ($this->strategies as $strategy) {
                    if ($strategy->getName() === 'popular') {
                        $remaining = $limit - count($recommendedProducts);
                        $popularProducts = $strategy->getRecommendations(
                            $userId,
                            $userProfile,
                            array_merge($viewedProductIds, $productIds),
                            $remaining
                        );

                        foreach ($popularProducts as $product) {
                            if (! in_array($product['id'], $productIds)) {
                                $recommendedProducts[] = $product;
                                $productIds[] = $product['id'];

                                if (count($recommendedProducts) >= $limit) {
                                    break;
                                }
                            }
                        }
                        break;
                    }
                }
            }

            return $recommendedProducts;
        } catch (\Exception $e) {
            Log::error('Error generating recommendations: '.$e->getMessage());

            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trackInteraction(int $userId, string $interactionType, int $itemId, array $metadata = []): bool
    {
        try {
            // Crear entidad de interacción
            $interaction = new UserInteractionEntity(
                $userId,
                $interactionType,
                $itemId,
                $metadata
            );

            // Guardar en el repositorio
            $this->userProfileRepository->saveUserInteraction($interaction);

            return true;
        } catch (\Exception $e) {
            Log::error('Error tracking user interaction: '.$e->getMessage());

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trackUserInteraction(int $userId, string $interactionType, int $itemId, array $metadata = []): void
    {
        $this->trackInteraction($userId, $interactionType, $itemId, $metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfile(int $userId): UserProfile
    {
        try {
            // Obtener perfil básico del repositorio
            $userProfile = $this->userProfileRepository->buildUserProfile($userId);

            // Enriquecer el perfil
            return $this->userProfileEnricher->enrichProfile($userProfile, $userId);
        } catch (\Exception $e) {
            Log::error('Error getting user profile: '.$e->getMessage());

            return new UserProfile([], [], [], [], 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUserProfileFormatted(int $userId): array
    {
        try {
            $userProfile = $this->getUserProfile($userId);

            return $this->profileFormatter->format($userProfile, $userId);
        } catch (\Exception $e) {
            Log::error('Error formatting user profile: '.$e->getMessage());

            return [
                'top_interests' => [],
                'recent_searches' => [],
                'recent_products' => [],
                'demographics' => [],
                'interaction_score' => 0,
                'profile_completeness' => 0,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateDemographicProfile(array $demographics): array
    {
        return $this->demographicGenerator->generate($demographics);
    }
}
