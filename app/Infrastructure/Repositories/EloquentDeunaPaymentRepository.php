<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\DeunaPaymentEntity;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Models\DeunaPayment;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EloquentDeunaPaymentRepository implements DeunaPaymentRepositoryInterface
{
    /**
     * Create a new DeUna payment record
     */
    public function create(DeunaPaymentEntity $payment): DeunaPaymentEntity
    {
        try {
            $data = $payment->toArray();
            unset($data['id']); // Remove ID for creation

            // Map entity field names to database field names
            if (isset($data['qr_code'])) {
                $data['qr_code_base64'] = $data['qr_code'];
                unset($data['qr_code']);
            }

            Log::info('Creating DeUna payment in database', [
                'data_keys' => array_keys($data),
                'has_qr_code_base64' => isset($data['qr_code_base64']),
                'qr_code_length' => isset($data['qr_code_base64']) ? strlen($data['qr_code_base64']) : 0,
            ]);

            $eloquentPayment = DeunaPayment::create($data);

            Log::info('DeUna payment created in database', [
                'id' => $eloquentPayment->id,
                'payment_id' => $eloquentPayment->payment_id,
                'order_id' => $eloquentPayment->order_id,
                'qr_code_saved' => $eloquentPayment->qr_code_base64 ? 'YES' : 'NO',
                'qr_code_length_saved' => $eloquentPayment->qr_code_base64 ? strlen($eloquentPayment->qr_code_base64) : 0,
            ]);

            return $this->mapToEntity($eloquentPayment);

        } catch (Exception $e) {
            Log::error('Error creating DeUna payment in database', [
                'error' => $e->getMessage(),
                'payment_data' => $payment->toArray(),
            ]);

            throw new Exception('Failed to create DeUna payment: '.$e->getMessage());
        }
    }

    /**
     * Find payment by payment ID
     */
    public function findByPaymentId(string $paymentId): ?DeunaPaymentEntity
    {
        try {
            $payment = DeunaPayment::where('payment_id', $paymentId)->first();

            return $payment ? $this->mapToEntity($payment) : null;

        } catch (Exception $e) {
            Log::error('Error finding DeUna payment by payment ID', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return null;
        }
    }

    /**
     * Find payment by order ID
     */
    public function findByOrderId(string $orderId): ?DeunaPaymentEntity
    {
        try {
            $payment = DeunaPayment::where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->first();

            return $payment ? $this->mapToEntity($payment) : null;

        } catch (Exception $e) {
            Log::error('Error finding DeUna payment by order ID', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return null;
        }
    }

    /**
     * Find payment by ID
     */
    public function findById(int $id): ?DeunaPaymentEntity
    {
        try {
            $payment = DeunaPayment::find($id);

            return $payment ? $this->mapToEntity($payment) : null;

        } catch (Exception $e) {
            Log::error('Error finding DeUna payment by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return null;
        }
    }

    /**
     * Update payment
     */
    public function update(DeunaPaymentEntity $payment): DeunaPaymentEntity
    {
        try {
            $eloquentPayment = DeunaPayment::where('payment_id', $payment->getPaymentId())->first();

            if (! $eloquentPayment) {
                throw new Exception('Payment not found for update');
            }

            $data = $payment->toArray();
            unset($data['id']); // Don't update the primary key

            // Map entity field names to database field names
            if (isset($data['qr_code'])) {
                $data['qr_code_base64'] = $data['qr_code'];
                unset($data['qr_code']);
            }

            $eloquentPayment->update($data);

            Log::info('DeUna payment updated in database', [
                'id' => $eloquentPayment->id,
                'payment_id' => $eloquentPayment->payment_id,
                'status' => $eloquentPayment->status,
            ]);

            return $this->mapToEntity($eloquentPayment->fresh());

        } catch (Exception $e) {
            Log::error('Error updating DeUna payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->getPaymentId(),
            ]);

            throw new Exception('Failed to update DeUna payment: '.$e->getMessage());
        }
    }

    /**
     * Get payments by status
     */
    public function getByStatus(string $status, int $limit = 100): Collection
    {
        try {
            $payments = DeunaPayment::where('status', $status)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $payments->map(fn ($payment) => $this->mapToEntity($payment));

        } catch (Exception $e) {
            Log::error('Error getting DeUna payments by status', [
                'error' => $e->getMessage(),
                'status' => $status,
                'limit' => $limit,
            ]);

            return collect();
        }
    }

    /**
     * Get payments for order
     */
    public function getPaymentsForOrder(string $orderId): Collection
    {
        try {
            $payments = DeunaPayment::where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->get();

            return $payments->map(fn ($payment) => $this->mapToEntity($payment));

        } catch (Exception $e) {
            Log::error('Error getting DeUna payments for order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return collect();
        }
    }

    /**
     * Get recent payments
     */
    public function getRecentPayments(int $limit = 50): Collection
    {
        try {
            $payments = DeunaPayment::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $payments->map(fn ($payment) => $this->mapToEntity($payment));

        } catch (Exception $e) {
            Log::error('Error getting recent DeUna payments', [
                'error' => $e->getMessage(),
                'limit' => $limit,
            ]);

            return collect();
        }
    }

    /**
     * Delete payment
     */
    public function delete(int $id): bool
    {
        try {
            $payment = DeunaPayment::find($id);

            if (! $payment) {
                return false;
            }

            $result = $payment->delete();

            Log::info('DeUna payment deleted from database', [
                'id' => $id,
                'payment_id' => $payment->payment_id,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Error deleting DeUna payment', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Get payments with filters
     */
    public function getWithFilters(array $filters = [], int $limit = 50, int $offset = 0): Collection
    {
        try {
            $query = DeunaPayment::query();

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['order_id'])) {
                $query->where('order_id', $filters['order_id']);
            }

            if (isset($filters['currency'])) {
                $query->where('currency', $filters['currency']);
            }

            if (isset($filters['from_date'])) {
                $query->where('created_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->where('created_at', '<=', $filters['to_date']);
            }

            if (isset($filters['amount_min'])) {
                $query->where('amount', '>=', $filters['amount_min']);
            }

            if (isset($filters['amount_max'])) {
                $query->where('amount', '<=', $filters['amount_max']);
            }

            if (isset($filters['customer_email'])) {
                $query->whereJsonContains('customer->email', $filters['customer_email']);
            }

            if (isset($filters['qr_type'])) {
                $query->where('qr_type', $filters['qr_type']);
            }

            $payments = $query->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return $payments->map(fn ($payment) => $this->mapToEntity($payment));

        } catch (Exception $e) {
            Log::error('Error getting DeUna payments with filters', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            return collect();
        }
    }

    /**
     * Count payments by status
     */
    public function countByStatus(string $status): int
    {
        try {
            return DeunaPayment::where('status', $status)->count();

        } catch (Exception $e) {
            Log::error('Error counting DeUna payments by status', [
                'error' => $e->getMessage(),
                'status' => $status,
            ]);

            return 0;
        }
    }

    /**
     * Find expired pending payments older than the specified threshold
     */
    public function findExpiredPendingPayments(\Carbon\Carbon $threshold): Collection
    {
        try {
            $payments = DeunaPayment::where('status', 'pending')
                ->where('created_at', '<', $threshold)
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info('Found expired pending payments', [
                'count' => $payments->count(),
                'threshold' => $threshold->toISOString(),
            ]);

            return $payments->map(fn ($payment) => $this->mapToEntity($payment));

        } catch (Exception $e) {
            Log::error('Error finding expired pending payments', [
                'error' => $e->getMessage(),
                'threshold' => $threshold->toISOString(),
            ]);

            return collect();
        }
    }

    /**
     * Update payment status
     */
    public function updateStatus(string $paymentId, string $status): bool
    {
        try {
            $eloquentPayment = DeunaPayment::where('payment_id', $paymentId)->first();

            if (! $eloquentPayment) {
                Log::warning('Payment not found for status update', [
                    'payment_id' => $paymentId,
                    'status' => $status,
                ]);

                return false;
            }

            $updateData = ['status' => $status];

            // Set appropriate timestamp based on status
            switch ($status) {
                case 'completed':
                    $updateData['completed_at'] = now();
                    break;
                case 'cancelled':
                    $updateData['cancelled_at'] = now();
                    break;
                case 'refunded':
                    $updateData['refunded_at'] = now();
                    break;
            }

            $result = $eloquentPayment->update($updateData);

            Log::info('Payment status updated', [
                'payment_id' => $paymentId,
                'old_status' => $eloquentPayment->getOriginal('status'),
                'new_status' => $status,
                'success' => $result,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Error updating payment status', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            return false;
        }
    }

    /**
     * Map Eloquent model to Domain Entity
     */
    private function mapToEntity(DeunaPayment $payment): DeunaPaymentEntity
    {
        return new DeunaPaymentEntity([
            'id' => $payment->id,
            'payment_id' => $payment->payment_id,
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'customer' => $payment->customer,
            'items' => $payment->items,
            'transaction_id' => $payment->transaction_id,
            'qr_code' => $payment->qr_code_base64,
            'payment_url' => $payment->payment_url,
            'metadata' => $payment->metadata,
            'failure_reason' => $payment->failure_reason,
            'refund_amount' => $payment->refund_amount,
            'cancel_reason' => $payment->cancel_reason,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'completed_at' => $payment->completed_at,
            'cancelled_at' => $payment->cancelled_at,
            'refunded_at' => $payment->refunded_at,
        ]);
    }
}
