<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\FeedbackEntity;

interface FeedbackRepositoryInterface
{
    /**
     * Create a new feedback.
     */
    public function create(FeedbackEntity $feedback): FeedbackEntity;

    /**
     * Update an existing feedback.
     */
    public function update(FeedbackEntity $feedback): FeedbackEntity;

    /**
     * Find feedback by ID.
     */
    public function findById(int $id): ?FeedbackEntity;

    /**
     * Get all feedbacks by user ID.
     */
    public function findByUserId(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Get all feedbacks by seller ID.
     */
    public function findBySellerId(int $sellerId, int $limit = 10, int $offset = 0): array;

    /**
     * Get all pending feedbacks.
     */
    public function findPending(int $limit = 10, int $offset = 0): array;

    /**
     * Get all approved feedbacks.
     */
    public function findApproved(int $limit = 10, int $offset = 0): array;

    /**
     * Get all feedbacks.
     */
    public function findAll(int $limit = 10, int $offset = 0): array;

    /**
     * Get total count of feedbacks.
     */
    public function count(array $filters = []): int;

    /**
     * Delete a feedback.
     */
    public function delete(int $id): bool;
}
