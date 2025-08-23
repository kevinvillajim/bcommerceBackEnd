<?php

namespace App\UseCases\Seller;

use App\Domain\Repositories\SellerRepositoryInterface;

class GetTopSellersUseCase
{
    private SellerRepositoryInterface $sellerRepository;

    /**
     * Constructor
     */
    public function __construct(SellerRepositoryInterface $sellerRepository)
    {
        $this->sellerRepository = $sellerRepository;
    }

    /**
     * Execute the use case to get top sellers by rating
     */
    public function executeByRating(int $limit = 10): array
    {
        return $this->sellerRepository->getTopSellersByRating($limit);
    }

    /**
     * Execute the use case to get top sellers by sales
     */
    public function executeBySales(int $limit = 10): array
    {
        return $this->sellerRepository->getTopSellersBySales($limit);
    }

    /**
     * Execute the use case to get featured sellers
     */
    public function executeFeatured(int $limit = 10): array
    {
        return $this->sellerRepository->getFeaturedSellers($limit);
    }
}
