<?php

namespace App\Domain\ValueObjects;

class UserProfile
{
    private array $interests = [];

    private array $searchHistory = [];

    private array $viewedProducts = [];

    private array $demographics = [];

    private int $interactionScore = 0;

    public function __construct(
        array $interests = [],
        array $searchHistory = [],
        array $viewedProducts = [],
        array $demographics = [],
        int $interactionScore = 0
    ) {
        $this->interests = $interests;
        $this->searchHistory = $searchHistory;
        $this->viewedProducts = $viewedProducts;
        $this->demographics = $demographics;
        $this->interactionScore = $interactionScore;
    }

    public function getInterests(): array
    {
        return $this->interests;
    }

    public function getSearchHistory(): array
    {
        return $this->searchHistory;
    }

    public function getViewedProducts(): array
    {
        return $this->viewedProducts;
    }

    public function getDemographics(): array
    {
        return $this->demographics;
    }

    public function getInteractionScore(): int
    {
        return $this->interactionScore;
    }

    public function addInterest(string $interest, int $weight = 1): self
    {
        if (! isset($this->interests[$interest])) {
            $this->interests[$interest] = $weight;
        } else {
            $this->interests[$interest] += $weight;
        }

        return $this;
    }

    public function addSearchTerm(string $term): self
    {
        $this->searchHistory[] = [
            'term' => $term,
            'timestamp' => time(),
        ];

        return $this;
    }

    public function addViewedProduct(int $productId): self
    {
        $this->viewedProducts[] = [
            'product_id' => $productId,
            'timestamp' => time(),
        ];

        return $this;
    }

    public function setDemographics(array $demographics): self
    {
        $this->demographics = $demographics;

        return $this;
    }

    public function incrementInteractionScore(int $value = 1): self
    {
        $this->interactionScore += $value;

        return $this;
    }
}
