<?php

namespace App\UseCases\Recommendation;

use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Domain\ValueObjects\UserProfile;

class GetUserProfileUseCase
{
    private RecommendationEngineInterface $recommendationEngine;

    public function __construct(RecommendationEngineInterface $recommendationEngine)
    {
        $this->recommendationEngine = $recommendationEngine;
    }

    public function execute(int $userId): array
    {
        $profile = $this->recommendationEngine->getUserProfile($userId);

        return $this->formatProfileData($profile);
    }

    private function formatProfileData(UserProfile $profile): array
    {
        // Obtener los 10 intereses principales
        $topInterests = array_slice($profile->getInterests(), 0, 10, true);

        // Obtener las 5 búsquedas más recientes
        $recentSearches = array_slice($profile->getSearchHistory(), 0, 5);

        // Obtener los 5 productos vistos más recientes
        $recentProducts = array_slice($profile->getViewedProducts(), 0, 5);

        return [
            'top_interests' => $topInterests,
            'recent_searches' => $recentSearches,
            'recent_products' => $recentProducts,
            'demographics' => $profile->getDemographics(),
            'interaction_score' => $profile->getInteractionScore(),
            'profile_completeness' => $this->calculateProfileCompleteness($profile),
        ];
    }

    private function calculateProfileCompleteness(UserProfile $profile): int
    {
        $score = 0;

        // Intereses
        $interestCount = count($profile->getInterests());
        if ($interestCount > 10) {
            $score += 30;
        } elseif ($interestCount > 5) {
            $score += 20;
        } elseif ($interestCount > 0) {
            $score += 10;
        }

        // Historial de búsqueda
        $searchCount = count($profile->getSearchHistory());
        if ($searchCount > 20) {
            $score += 25;
        } elseif ($searchCount > 10) {
            $score += 15;
        } elseif ($searchCount > 0) {
            $score += 5;
        }

        // Productos vistos
        $viewedCount = count($profile->getViewedProducts());
        if ($viewedCount > 30) {
            $score += 25;
        } elseif ($viewedCount > 15) {
            $score += 15;
        } elseif ($viewedCount > 0) {
            $score += 5;
        }

        // Demografía
        if (! empty($profile->getDemographics())) {
            $score += 10;

            if (isset($profile->getDemographics()['age'])) {
                $score += 5;
            }

            if (isset($profile->getDemographics()['gender'])) {
                $score += 5;
            }
        }

        return min(100, $score);
    }
}
