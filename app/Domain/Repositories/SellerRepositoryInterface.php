<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SellerEntity;

interface SellerRepositoryInterface
{
    /**
     * Find a seller by ID
     */
    public function findById(int $id): ?SellerEntity;

    /**
     * Find a seller by ID (alias)
     */
    public function find(int $id): ?SellerEntity;

    /**
     * Find a seller by user ID
     */
    public function findByUserId(int $userId): ?SellerEntity;

    /**
     * Create a new seller
     */
    public function create(SellerEntity $sellerEntity): SellerEntity;

    /**
     * Update a seller
     */
    public function update(SellerEntity $sellerEntity): SellerEntity;

    /**
     * Get top sellers by rating
     */
    public function getTopSellersByRating(int $limit = 10): array;

    /**
     * Get featured sellers
     */
    public function getFeaturedSellers(int $limit = 10): array;

    /**
     * Get sellers with the most sales
     */
    public function getTopSellersBySales(int $limit = 10): array;
}
