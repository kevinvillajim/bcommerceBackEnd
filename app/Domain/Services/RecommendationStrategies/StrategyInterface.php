<?php

namespace App\Domain\Services\RecommendationStrategies;

use App\Domain\ValueObjects\UserProfile;

interface StrategyInterface
{
    /**
     * Genera recomendaciones basadas en la estrategia específica.
     */
    public function getRecommendations(
        int $userId,
        ?UserProfile $userProfile,
        array $excludeProductIds = [],
        int $limit = 10
    ): array;

    /**
     * Devuelve el nombre de la estrategia.
     */
    public function getName(): string;
}
