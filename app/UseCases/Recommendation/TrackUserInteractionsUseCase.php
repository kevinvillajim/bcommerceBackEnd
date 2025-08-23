<?php

namespace App\UseCases\Recommendation;

use App\Domain\Interfaces\RecommendationEngineInterface;

class TrackUserInteractionsUseCase
{
    private RecommendationEngineInterface $recommendationEngine;

    public function __construct(RecommendationEngineInterface $recommendationEngine)
    {
        $this->recommendationEngine = $recommendationEngine;
    }

    public function execute(
        int $userId,
        string $interactionType,
        int $itemId,
        array $metadata = []
    ): void {
        $this->recommendationEngine->trackUserInteraction(
            $userId,
            $interactionType,
            $itemId,
            $metadata
        );
    }
}
