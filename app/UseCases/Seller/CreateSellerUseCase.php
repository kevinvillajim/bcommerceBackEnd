<?php

namespace App\UseCases\Seller;

use App\Domain\Entities\SellerEntity;
use App\Domain\Repositories\SellerRepositoryInterface;

class CreateSellerUseCase
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
     * Execute the use case
     */
    public function execute(int $userId, string $storeName, ?string $description = null): SellerEntity
    {
        // Check if user is already a seller
        $existingSeller = $this->sellerRepository->findByUserId($userId);
        if ($existingSeller) {
            throw new \InvalidArgumentException('User is already a seller');
        }

        // Create a new seller entity
        $sellerEntity = new SellerEntity(
            $userId,
            $storeName,
            $description,
            'pending', // New sellers start with pending status
            'none',    // New sellers start with no verification
            10.0,      // Default commission rate
            0,         // Initial sales count
            false      // Not featured by default
        );

        // Save the seller entity
        return $this->sellerRepository->create($sellerEntity);
    }
}
