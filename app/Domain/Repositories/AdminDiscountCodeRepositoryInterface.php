<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AdminDiscountCodeEntity;

interface AdminDiscountCodeRepositoryInterface
{
    /**
     * Create a new admin discount code.
     */
    public function create(AdminDiscountCodeEntity $discountCode): AdminDiscountCodeEntity;

    /**
     * Update an existing admin discount code.
     */
    public function update(AdminDiscountCodeEntity $discountCode): AdminDiscountCodeEntity;

    /**
     * Find discount code by ID.
     */
    public function findById(int $id): ?AdminDiscountCodeEntity;

    /**
     * Find discount code by code string.
     */
    public function findByCode(string $code): ?AdminDiscountCodeEntity;

    /**
     * Get all discount codes with filters and pagination.
     */
    public function findAll(array $filters = [], int $limit = 10, int $offset = 0): array;

    /**
     * Get count of discount codes with filters.
     */
    public function count(array $filters = []): int;

    /**
     * Delete a discount code.
     */
    public function delete(int $id): bool;

    /**
     * Get valid (unused and not expired) discount codes.
     */
    public function findValid(int $limit = 10, int $offset = 0): array;

    /**
     * Get expired discount codes.
     */
    public function findExpired(int $limit = 10, int $offset = 0): array;

    /**
     * Get used discount codes.
     */
    public function findUsed(int $limit = 10, int $offset = 0): array;

    /**
     * Check if a code already exists.
     */
    public function codeExists(string $code, ?int $excludeId = null): bool;
}
