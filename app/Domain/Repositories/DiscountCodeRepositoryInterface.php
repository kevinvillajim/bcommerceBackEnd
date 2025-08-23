<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\DiscountCodeEntity;

interface DiscountCodeRepositoryInterface
{
    /**
     * Create a new discount code.
     */
    public function create(DiscountCodeEntity $discountCode): DiscountCodeEntity;

    /**
     * Update an existing discount code.
     */
    public function update(DiscountCodeEntity $discountCode): DiscountCodeEntity;

    /**
     * Find discount code by ID.
     */
    public function findById(int $id): ?DiscountCodeEntity;

    /**
     * Find discount code by code.
     */
    public function findByCode(string $code): ?DiscountCodeEntity;

    /**
     * Find discount code by feedback ID.
     */
    public function findByFeedbackId(int $feedbackId): ?DiscountCodeEntity;

    /**
     * Get all active discount codes.
     */
    public function findActive(int $limit = 10, int $offset = 0): array;

    /**
     * Get all used discount codes.
     */
    public function findUsed(int $limit = 10, int $offset = 0): array;

    /**
     * Get all discount codes used by a user.
     */
    public function findByUserId(int $userId, int $limit = 10, int $offset = 0, bool $onlyActive = true): array;

    /**
     * Count discount codes by user ID.
     */
    public function countByUserId(int $userId, bool $onlyActive = true): int;

    /**
     * Check if code exists.
     */
    public function codeExists(string $code): bool;

    /**
     * Generate a unique code.
     */
    public function generateUniqueCode(int $length = 6): string;
}
