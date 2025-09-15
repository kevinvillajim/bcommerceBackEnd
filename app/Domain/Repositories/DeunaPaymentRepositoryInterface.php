<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\DeunaPaymentEntity;
use Illuminate\Support\Collection;

interface DeunaPaymentRepositoryInterface
{
    /**
     * Create a new DeUna payment record
     */
    public function create(DeunaPaymentEntity $payment): DeunaPaymentEntity;

    /**
     * Find payment by payment ID
     */
    public function findByPaymentId(string $paymentId): ?DeunaPaymentEntity;

    /**
     * Find payment by order ID
     */
    public function findByOrderId(string $orderId): ?DeunaPaymentEntity;

    /**
     * Find payment by ID
     */
    public function findById(int $id): ?DeunaPaymentEntity;

    /**
     * Update payment
     */
    public function update(DeunaPaymentEntity $payment): DeunaPaymentEntity;

    /**
     * Get payments by status
     *
     * @return Collection<DeunaPaymentEntity>
     */
    public function getByStatus(string $status, int $limit = 100): Collection;

    /**
     * Get payments for order
     *
     * @return Collection<DeunaPaymentEntity>
     */
    public function getPaymentsForOrder(string $orderId): Collection;

    /**
     * Get recent payments
     *
     * @return Collection<DeunaPaymentEntity>
     */
    public function getRecentPayments(int $limit = 50): Collection;

    /**
     * Delete payment
     */
    public function delete(int $id): bool;

    /**
     * Get payments with filters
     *
     * @return Collection<DeunaPaymentEntity>
     */
    public function getWithFilters(array $filters = [], int $limit = 50, int $offset = 0): Collection;

    /**
     * Count payments by status
     */
    public function countByStatus(string $status): int;

    /**
     * Find expired pending payments older than the specified threshold
     *
     * @return Collection<DeunaPaymentEntity>
     */
    public function findExpiredPendingPayments(\Carbon\Carbon $threshold): Collection;

    /**
     * Update payment status
     */
    public function updateStatus(string $paymentId, string $status): bool;
}
