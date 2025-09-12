<?php

namespace App\UseCases\Payment;

use App\Domain\Interfaces\DeunaServiceInterface;
use App\Domain\Repositories\DeunaPaymentRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
use App\Models\Configuration;
use App\Services\ConfigurationService;
use App\Services\OrderStatusHandler;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandleDeunaWebhookUseCase
{
    public function __construct(
        private DeunaServiceInterface $deunaService,
        private DeunaPaymentRepositoryInterface $deunaPaymentRepository,
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private OrderStatusHandler $orderStatusHandler,
        private PricingCalculatorService $pricingService,
        private ConfigurationService $configService
    ) {}

    /**
     * Handle DeUna webhook notification
     *
     * @throws Exception
     */
    public function execute(array $webhookData, string $signature = ''): array
    {
        try {
            Log::info('Processing DeUna webhook', [
                'event' => $webhookData['event'] ?? 'unknown',
                'payment_id' => $webhookData['payment_id'] ?? $webhookData['idTransacionReference'] ?? null,
            ]);

            // Verify webhook signature if provided
            if (! empty($signature)) {
                $payload = json_encode($webhookData);
                if (! $this->deunaService->verifyWebhookSignature($payload, $signature)) {
                    throw new Exception('Invalid webhook signature');
                }
                Log::info('Webhook signature verified successfully');
            }

            // Extract payment information
            $paymentId = $this->extractPaymentId($webhookData);
            $status = $this->extractStatus($webhookData);
            $eventType = $this->extractEventType($webhookData);

            // Find the payment in our database
            $payment = $this->deunaPaymentRepository->findByPaymentId($paymentId);
            if (! $payment) {
                throw new Exception('Payment not found in database: '.$paymentId);
            }

            // Process webhook based on event type or status
            $result = $this->processWebhookEvent($payment, $eventType, $status, $webhookData);

            // Update order status if needed
            if ($result['order_status_updated']) {
                $this->updateOrderStatus($payment->getOrderId(), $status, $webhookData);
            }

            Log::info('DeUna webhook processed successfully', [
                'payment_id' => $paymentId,
                'event' => $eventType,
                'status' => $status,
                'order_updated' => $result['order_status_updated'],
            ]);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'event' => $eventType,
                'status' => $status,
                'processed' => true,
                'message' => $result['message'],
            ];

        } catch (Exception $e) {
            Log::error('Error processing DeUna webhook', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('Failed to process webhook: '.$e->getMessage());
        }
    }

    /**
     * Extract payment ID from webhook data
     * Based on Official DeUna Webhook Documentation
     */
    private function extractPaymentId(array $webhookData): string
    {
        // DeUna webhook sends 'idTransaction' as the main identifier
        return $webhookData['idTransaction']
            ?? $webhookData['payment_id']
            ?? $webhookData['idTransacionReference']
            ?? $webhookData['data']['payment_id']
            ?? $webhookData['data']['idTransaction']
            ?? $webhookData['data']['idTransacionReference']
            ?? throw new Exception('Payment ID not found in webhook data');
    }

    /**
     * Extract status from webhook data
     * Based on Official DeUna Webhook Documentation
     */
    private function extractStatus(array $webhookData): string
    {
        $status = $webhookData['status']
            ?? $webhookData['data']['status']
            ?? $webhookData['paymentStatus']
            ?? 'unknown';

        // Map DeUna webhook statuses to our internal statuses
        // DeUna sends "SUCCESS" for completed payments
        return match (strtoupper($status)) {
            'SUCCESS', 'COMPLETED', 'PAID', 'SUCCESSFUL' => 'completed',
            'PENDING', 'PROCESSING' => 'pending',
            'FAILED', 'ERROR', 'DECLINED', 'REJECTED' => 'failed',
            'CANCELLED', 'CANCELED' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => strtolower($status)
        };
    }

    /**
     * Extract event type from webhook data
     * Based on Official DeUna Webhook Documentation
     * Note: DeUna only sends webhooks for successful payments
     */
    private function extractEventType(array $webhookData): string
    {
        // Check if explicit event type is provided
        if (isset($webhookData['event']) || isset($webhookData['eventType'])) {
            return $webhookData['event'] ?? $webhookData['eventType'];
        }

        // Determine event type based on status
        // DeUna documentation states they only send webhooks for successful payments
        $status = strtoupper($webhookData['status'] ?? 'UNKNOWN');

        return match ($status) {
            'SUCCESS' => 'payment.completed',
            'PENDING' => 'payment.pending',
            'FAILED', 'ERROR' => 'payment.failed',
            'CANCELLED' => 'payment.cancelled',
            'REFUNDED' => 'payment.refunded',
            default => 'payment.status_changed'
        };
    }

    /**
     * Process webhook event based on type and status
     */
    private function processWebhookEvent($payment, string $eventType, string $status, array $webhookData): array
    {
        $orderStatusUpdated = false;
        $message = '';

        switch ($eventType) {
            case 'payment.completed':
            case 'payment.successful':
                $this->handlePaymentCompleted($payment, $webhookData);
                $orderStatusUpdated = true;
                $message = 'Payment completed successfully';
                break;

            case 'payment.failed':
            case 'payment.declined':
                $this->handlePaymentFailed($payment, $webhookData);
                $orderStatusUpdated = true;
                $message = 'Payment failed';
                break;

            case 'payment.cancelled':
                $this->handlePaymentCancelled($payment, $webhookData);
                $orderStatusUpdated = true;
                $message = 'Payment cancelled';
                break;

            case 'payment.refunded':
                $this->handlePaymentRefunded($payment, $webhookData);
                $orderStatusUpdated = true;
                $message = 'Payment refunded';
                break;

            case 'payment.pending':
            case 'payment.processing':
                $this->handlePaymentPending($payment, $webhookData);
                $orderStatusUpdated = false;
                $message = 'Payment is pending';
                break;

            default:
                // Generic status update
                $this->handleStatusChange($payment, $status, $webhookData);
                $orderStatusUpdated = ($status === 'completed');
                $message = "Payment status updated to: {$status}";
                break;
        }

        return [
            'order_status_updated' => $orderStatusUpdated,
            'message' => $message,
        ];
    }

    /**
     * Handle payment completed event
     */
    private function handlePaymentCompleted($payment, array $webhookData): void
    {
        $payment->markAsCompleted();

        // Update additional transaction details if provided
        if (isset($webhookData['transaction_id']) || isset($webhookData['data']['transaction_id'])) {
            $transactionId = $webhookData['transaction_id'] ?? $webhookData['data']['transaction_id'];
            $payment->setTransactionId($transactionId);
        }

        $this->deunaPaymentRepository->update($payment);

        // BEST PRACTICE: Create order when payment is confirmed
        $this->createOrderFromPayment($payment, $webhookData);

        Log::info('Payment marked as completed and order created', [
            'payment_id' => $payment->getPaymentId(),
            'order_id' => $payment->getOrderId(),
        ]);
    }

    /**
     * Handle payment failed event
     */
    private function handlePaymentFailed($payment, array $webhookData): void
    {
        $reason = $webhookData['failure_reason']
            ?? $webhookData['data']['failure_reason']
            ?? $webhookData['error_message']
            ?? 'Payment failed';

        $payment->setStatus('failed');
        $payment->setFailureReason($reason);

        $this->deunaPaymentRepository->update($payment);

        Log::info('Payment marked as failed', [
            'payment_id' => $payment->getPaymentId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Handle payment cancelled event
     */
    private function handlePaymentCancelled($payment, array $webhookData): void
    {
        $reason = $webhookData['cancel_reason']
            ?? $webhookData['data']['cancel_reason']
            ?? 'Payment cancelled by user';

        $payment->markAsCancelled($reason);

        $this->deunaPaymentRepository->update($payment);

        Log::info('Payment marked as cancelled', [
            'payment_id' => $payment->getPaymentId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Handle payment refunded event
     */
    private function handlePaymentRefunded($payment, array $webhookData): void
    {
        $refundAmount = isset($webhookData['refund_amount'])
            ? (float) $webhookData['refund_amount']
            : $payment->getAmount();

        $payment->markAsRefunded($refundAmount);

        $this->deunaPaymentRepository->update($payment);

        Log::info('Payment marked as refunded', [
            'payment_id' => $payment->getPaymentId(),
            'refund_amount' => $refundAmount,
        ]);
    }

    /**
     * Handle payment pending event
     */
    private function handlePaymentPending($payment, array $webhookData): void
    {
        $payment->setStatus('pending');

        $this->deunaPaymentRepository->update($payment);

        Log::info('Payment status updated to pending', [
            'payment_id' => $payment->getPaymentId(),
        ]);
    }

    /**
     * Handle generic status change
     */
    private function handleStatusChange($payment, string $status, array $webhookData): void
    {
        $payment->setStatus($status);

        $this->deunaPaymentRepository->update($payment);

        Log::info('Payment status updated', [
            'payment_id' => $payment->getPaymentId(),
            'new_status' => $status,
        ]);
    }

    /**
     * Update order status based on payment status
     */
    private function updateOrderStatus(string $orderId, string $paymentStatus, array $webhookData): void
    {
        try {
            // 🚨 CRITICAL FIX: Try to find order by numeric ID first, then by order_number
            $order = null;
            
            // Try to find by numeric ID (if orderId is numeric)
            if (is_numeric($orderId)) {
                $order = $this->orderRepository->findById((int)$orderId);
                Log::info('🔍 CRITICAL: Searching order by numeric ID', [
                    'order_id' => $orderId,
                    'found' => $order !== null
                ]);
            }
            
            // If not found, try to find by order_number (string identifier)
            if (! $order) {
                // Use Eloquent directly to search by order_number
                $eloquentOrder = \App\Models\Order::where('order_number', $orderId)->first();
                if ($eloquentOrder) {
                    $order = $this->orderRepository->findById($eloquentOrder->id);
                }
                Log::info('🔍 CRITICAL: Searching order by order_number', [
                    'order_number' => $orderId,
                    'eloquent_found' => $eloquentOrder !== null,
                    'repository_found' => $order !== null,
                    'eloquent_id' => $eloquentOrder ? $eloquentOrder->id : null
                ]);
            }
            
            if (! $order) {
                Log::warning('Order not found for status update', [
                    'searched_id' => $orderId,
                    'searched_as_numeric' => is_numeric($orderId),
                    'searched_as_order_number' => true
                ]);

                return;
            }

            $orderStatus = $this->mapPaymentStatusToOrderStatus($paymentStatus);
            
            // Use the actual numeric order ID for the update
            $actualOrderId = $order->getId();
            $this->orderStatusHandler->updateOrderStatus($actualOrderId, $orderStatus);
            
            // ✅ IMPORTANTE: Crear seller_orders cuando el pago está completo (Deuna)
            if ($paymentStatus === 'completed') {
                Log::info('🚨 CRITICAL: About to create seller orders for DeUna payment', [
                    'order_id' => $actualOrderId,
                    'payment_status' => $paymentStatus,
                ]);
                
                $this->createSellerOrdersForDeuna($order);
                
                // ✅ VERIFICAR INMEDIATAMENTE SI SE CREÓ (verification via direct DB query below)
                Log::info('🔍 CRITICAL VERIFICATION: seller orders creation completed', [
                    'order_id' => $actualOrderId,
                    'verification_method' => 'direct_db_query_below',
                ]);
                
                // ✅ VERIFICAR EN BD DIRECTAMENTE
                $dbOrder = \App\Models\Order::find($actualOrderId);
                Log::info('🔍 DB DIRECT CHECK: seller_order_id in database', [
                    'order_id' => $actualOrderId,
                    'db_seller_order_id' => $dbOrder ? $dbOrder->seller_order_id : 'ORDER_NOT_FOUND_IN_DB',
                ]);
                
                // 🚨 FALLBACK: Si seller_order_id es NULL, buscar el seller_order y forzar la actualización
                if ($dbOrder && $dbOrder->seller_order_id === null) {
                    Log::error('🚨 CRITICAL BUG: seller_order_id is NULL after creation, applying FALLBACK');
                    
                    $sellerOrder = \App\Models\SellerOrder::where('order_id', $actualOrderId)->first();
                    if ($sellerOrder) {
                        Log::info('🔧 FALLBACK: Found seller_order, forcing update', [
                            'seller_order_id' => $sellerOrder->id,
                            'order_id' => $actualOrderId
                        ]);
                        
                        // Forzar actualización directa en BD
                        \App\Models\Order::where('id', $actualOrderId)->update([
                            'seller_order_id' => $sellerOrder->id
                        ]);
                        
                        Log::info('✅ FALLBACK: seller_order_id updated successfully via direct DB query');
                    } else {
                        Log::error('❌ FALLBACK: No seller_order found for order_id ' . $actualOrderId);
                    }
                }
            }

            Log::info('Order status updated based on payment status', [
                'order_id' => $actualOrderId,
                'original_search_id' => $orderId,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'seller_orders_created' => ($paymentStatus === 'completed'),
            ]);

        } catch (Exception $e) {
            Log::error('Error updating order status', [
                'error' => $e->getMessage(),
                'search_id' => $orderId,
                'payment_status' => $paymentStatus,
            ]);
        }
    }

    /**
     * Map payment status to order status
     */
    private function mapPaymentStatusToOrderStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'completed' => 'paid',
            'failed' => 'payment_failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'pending' => 'payment_pending',
            default => 'pending'
        };
    }

    /**
     * 🚨 CRITICAL FIX: Create order with idempotency protection against duplicate webhooks
     * BEST PRACTICE: Webhook creates the order to ensure consistency
     */
    private function createOrderFromPayment($payment, array $webhookData): void
    {
        try {
            $orderId = $payment->getOrderId();
            
            // 🚨 CRITICAL FIX: Implement idempotency key to prevent duplicate processing
            $idempotencyKey = $webhookData['idempotency_key'] ?? $payment->getPaymentId() ?? "webhook_{$orderId}";
            $cacheKey = "webhook_processed_{$idempotencyKey}";
            
            // Check if webhook was already processed
            $processed = Cache::get($cacheKey);
            if ($processed) {
                Log::info('🚨 CRITICAL: Webhook already processed, preventing duplicate', [
                    'idempotency_key' => $idempotencyKey,
                    'payment_id' => $payment->getPaymentId(),
                    'order_id' => $orderId,
                    'processed_at' => $processed['processed_at'],
                    'action' => 'DUPLICATE_PREVENTION'
                ]);
                return; // Exit early to prevent duplicate processing
            }
            
            // 🚨 CRITICAL: Mark as processing immediately to prevent race conditions
            Cache::put($cacheKey, [
                'processed_at' => now()->toISOString(),
                'payment_id' => $payment->getPaymentId(),
                'order_id' => $orderId,
                'status' => 'processing'
            ], 3600); // 1 hour TTL
            
            Log::info('🔒 CRITICAL: Webhook marked as processing', [
                'idempotency_key' => $idempotencyKey,
                'payment_id' => $payment->getPaymentId(),
                'order_id' => $orderId
            ]);

            // Check if order already exists (idempotency)
            $existingOrder = $this->orderRepository->findById($orderId);
            if ($existingOrder) {
                Log::info('Order already exists, updating status only', [
                    'order_id' => $orderId,
                    'existing_status' => $existingOrder->getStatus(),
                ]);

                $this->orderStatusHandler->updateOrderStatus($orderId, 'paid');
                
                // 🚨 CRITICAL: Mark as completed in cache
                Cache::put($cacheKey, [
                    'processed_at' => now()->toISOString(),
                    'payment_id' => $payment->getPaymentId(),
                    'order_id' => $orderId,
                    'status' => 'completed',
                    'action' => 'order_existed_status_updated'
                ], 3600);

                return;
            }

            // Extract order data from payment
            $customerData = $payment->getCustomer();
            $items = $payment->getItems();
            $amount = $payment->getAmount();
            $metadata = $payment->getMetadata();

            // 🔧 CRITICAL FIX: Always use original items to ensure product_id preservation
            $originalItems = $payment->getItems();

            // Check if webhook provides items for information/validation only
            if (isset($webhookData['items']) && ! empty($webhookData['items'])) {
                $webhookItems = $webhookData['items'];

                // 🔍 LOG: Compare webhook vs original items structure
                Log::info('🔍 WEBHOOK vs ORIGINAL ITEMS COMPARISON', [
                    'payment_id' => $payment->getPaymentId(),
                    'webhook_items_count' => count($webhookItems),
                    'original_items_count' => count($originalItems),
                    'webhook_first_item' => $webhookItems[0] ?? null,
                    'original_first_item' => $originalItems[0] ?? null,
                    'webhook_has_product_id' => isset($webhookItems[0]['product_id']) ? 'YES' : 'NO',
                    'original_has_product_id' => isset($originalItems[0]['product_id']) ? 'YES' : 'NO',
                ]);

                // 🚨 ALWAYS USE ORIGINAL ITEMS for order creation to preserve product_id
                $items = $originalItems;

                Log::info('✅ DECISION: Using ORIGINAL items to preserve product_id integrity', [
                    'payment_id' => $payment->getPaymentId(),
                    'strategy' => 'original_items_only',
                    'items_count' => count($items),
                    'first_item_has_product_id' => isset($items[0]['product_id']) ? 'YES' : 'NO',
                    'first_item_product_id' => $items[0]['product_id'] ?? 'NULL',
                    'all_product_ids' => array_map(fn ($item) => $item['product_id'] ?? 'NULL', $items),
                ]);
            } else {
                // Use original items (standard case)
                $items = $originalItems;
                Log::info('📋 Using original payment items (no webhook items provided)', [
                    'payment_id' => $payment->getPaymentId(),
                    'original_items_count' => count($items),
                    'first_item_has_product_id' => isset($items[0]['product_id']) ? 'YES' : 'NO',
                ]);
            }

            // Get user_id from metadata (preferred) or find by email (fallback)
            $userId = null;
            if (isset($metadata['user_id']) && ! empty($metadata['user_id'])) {
                $userId = (int) $metadata['user_id'];
                Log::info('Using user_id from payment metadata', ['user_id' => $userId]);
            } else {
                // Use the repository method to find or create user
                $user = \App\Models\User::where('email', $customerData['email'])->first();
                if (! $user) {
                    // Create minimal user for webhook order
                    $user = new \App\Models\User;
                    $user->email = $customerData['email'];
                    $user->name = $customerData['name'] ?? 'DeUna Customer';
                    $user->password = bcrypt(Str::random(32));
                    $user->email_verified_at = now();
                    $user->save();

                    Log::info('Created new user from webhook order', [
                        'user_id' => $user->id,
                        'email' => $customerData['email'],
                        'name' => $user->name,
                    ]);
                }

                $userId = $user->id;
                Log::info('Using user_id from email lookup', ['user_id' => $userId, 'email' => $customerData['email']]);
            }

            // 🔧 NUEVO: Obtener seller_id del primer producto
            $sellerId = null;
            if (! empty($items)) {
                $firstProductId = $items[0]['product_id'] ?? null;
                if ($firstProductId) {
                    $firstProduct = $this->productRepository->findById($firstProductId);
                    if ($firstProduct) {
                        $sellerId = $firstProduct->getSellerId();
                        Log::info('Seller ID extraído del primer producto', [
                            'product_id' => $firstProductId,
                            'seller_id' => $sellerId,
                        ]);
                    }
                }
            }

            // 🔧 CORREGIDO: Usar PricingCalculatorService centralizado para consistencia
            $calculatedTotals = $this->calculateTotalsUsingCentralizedService($items, $userId, $amount);

            // 🔧 NUEVO: Reducir inventario de productos
            $this->reduceProductStock($items);

            // Create order data structure with correct pricing
            $orderData = [
                'id' => $orderId,
                'user_id' => $userId,
                'seller_id' => $sellerId, // 🔧 AGREGADO: seller_id del producto
                'customer' => [
                    'name' => $customerData['name'] ?? 'Unknown Customer',
                    'email' => $customerData['email'] ?? '',
                    'phone' => $customerData['phone'] ?? '',
                ],
                'items' => $this->prepareOrderItems($items, $amount),
                'payment' => [
                    'method' => 'deuna',
                    'status' => 'paid',
                    'amount' => $amount,
                    'currency' => $payment->getCurrency(),
                    'payment_id' => $payment->getPaymentId(),
                    'transaction_id' => $payment->getTransactionId(),
                ],
                'totals' => $calculatedTotals,
                'status' => 'paid',
                'payment_status' => 'paid',
                'created_via' => 'deuna_webhook',
                'payment_details' => [ // 🔧 CAMBIADO: usar payment_details en lugar de webhook_data
                    'payment_method' => 'deuna',
                    'payment_id' => $payment->getPaymentId(),
                    'transaction_id' => $payment->getTransactionId() ?? $webhookData['transaction_id'] ?? null,
                    'transfer_number' => $webhookData['transferNumber'] ?? null,
                    'branch_id' => $webhookData['branchId'] ?? null,
                    'pos_id' => $webhookData['posId'] ?? null,
                    'customer_identification' => $webhookData['customerIdentification'] ?? null,
                    'customer_full_name' => $webhookData['customerFullName'] ?? null,
                    'processed_at' => now()->format('Y-m-d H:i:s'), // 🔧 CORREGIDO: usar formato de fecha del backend
                    'amount' => $amount,
                    'currency' => $payment->getCurrency(),
                    'status' => 'completed',
                ],
            ];

            // Create the order
            $order = $this->orderRepository->createFromWebhook($orderData);

            // 🔥 NUEVO: Disparar evento OrderCreated para invalidar cache del carrito
            Log::info('🚀 DeUna Webhook: Disparando evento OrderCreated para invalidar cache', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'payment_method' => 'deuna'
            ]);
            
            event(new \App\Events\OrderCreated(
                (int) $orderId, // ✅ CORREGIDO: Convertir a int como espera el evento
                $userId,
                $sellerId,
                ['payment_method' => 'deuna', 'created_via' => 'webhook']
            ));

            // 🚨 CRITICAL: Mark webhook as successfully completed
            Cache::put($cacheKey, [
                'processed_at' => now()->toISOString(),
                'completed_at' => now()->toISOString(),
                'payment_id' => $payment->getPaymentId(),
                'order_id' => $orderId,
                'status' => 'completed_successfully',
                'action' => 'order_created_successfully'
            ], 3600);

            Log::info('Order created successfully from webhook', [
                'order_id' => $orderId,
                'payment_id' => $payment->getPaymentId(),
                'amount' => $amount,
                'customer_email' => $customerData['email'] ?? null,
                'idempotency_key' => $idempotencyKey
            ]);

        } catch (\Exception $e) {
            // 🚨 CRITICAL: Mark webhook as failed in cache to prevent retry loops but allow manual intervention
            Cache::put($cacheKey, [
                'processed_at' => now()->toISOString(),
                'failed_at' => now()->toISOString(),
                'payment_id' => $payment->getPaymentId(),
                'order_id' => $orderId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'action' => 'order_creation_failed'
            ], 3600);

            Log::error('Failed to create order from payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->getPaymentId(),
                'order_id' => $payment->getOrderId(),
                'trace' => $e->getTraceAsString(),
                'idempotency_key' => $idempotencyKey ?? 'unknown'
            ]);

            // Don't throw exception to avoid webhook retry loops
            // Order creation failure should not fail the webhook
        }
    }

    /**
     * Prepare order items from payment items
     */
    private function prepareOrderItems(array $paymentItems, float $totalAmount): array
    {
        if (empty($paymentItems)) {
            // Create a generic item if no specific items are provided
            return [
                [
                    'product_id' => null,
                    'name' => 'DeUna Payment',
                    'quantity' => 1,
                    'price' => $totalAmount,
                    'subtotal' => $totalAmount,
                ],
            ];
        }

        return array_map(function ($item) {
            // First try to get product_id directly from item data
            $productId = $item['product_id'] ?? null;

            // If not found, try to extract from description (fallback for frontend compatibility)
            if (! $productId && isset($item['description']) && strpos($item['description'], 'product_id:') !== false) {
                preg_match('/product_id:(\d+)/', $item['description'], $matches);
                $productId = isset($matches[1]) ? (int) $matches[1] : null;
            }

            // 🔧 NUEVO: Calcular precio con descuento para compatibilidad con Datafast
            $originalPrice = $item['price'] ?? 0;
            $priceWithDiscount = $originalPrice;

            if ($productId) {
                $product = $this->productRepository->findById($productId);
                if ($product) {
                    $discountPercentage = $product->getDiscountPercentage();
                    $priceWithDiscount = $originalPrice * (1 - $discountPercentage / 100);
                }
            }

            // 🚨 CRITICAL FIX: Validación estricta ANTES de cualquier procesamiento
            if ($productId === null || !is_numeric($productId) || $productId <= 0) {
                Log::critical('🚨 CRITICAL VALIDATION FAILURE: Invalid product_id detected', [
                    'product_id' => $productId,
                    'product_id_type' => gettype($productId),
                    'item_index' => array_search($item, $paymentItems),
                    'item_name' => $item['name'] ?? 'Unknown Item',
                    'item_data' => $item,
                    'payment_id' => isset($payment) ? $payment->getPaymentId() : 'unknown',
                    'action' => 'TRANSACTION_ROLLBACK_INITIATED'
                ]);
                
                // 🚨 CRITICAL: Rollback transaction immediately to prevent data corruption
                DB::rollBack();
                throw new \Exception("CRITICAL: product_id validation failed. Value: " . json_encode($productId) . " is not valid");
            }

            // 🚨 CRITICAL FIX: Verificar que el producto existe en la base de datos
            try {
                $productExists = $this->productRepository->findById($productId);
                if (!$productExists) {
                    Log::critical('🚨 CRITICAL: Referenced product does not exist in database', [
                        'product_id' => $productId,
                        'item_name' => $item['name'] ?? 'Unknown Item',
                        'payment_id' => isset($payment) ? $payment->getPaymentId() : 'unknown'
                    ]);
                    
                    DB::rollBack();
                    throw new \Exception("CRITICAL: Product {$productId} does not exist in database");
                }
                
                Log::info('✅ Product validation passed', [
                    'product_id' => $productId,
                    'product_name' => $productExists->getName()
                ]);
                
            } catch (\Exception $e) {
                Log::critical('🚨 CRITICAL: Product validation query failed', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                
                DB::rollBack();
                throw new \Exception("CRITICAL: Product validation failed for ID {$productId}: " . $e->getMessage());
            }

            return [
                'product_id' => $productId,
                'name' => $item['name'] ?? 'Unknown Item',
                'quantity' => $item['quantity'] ?? 1,
                'price' => $priceWithDiscount, // 🔧 USAR PRECIO CON DESCUENTO para compatibilidad
                'subtotal' => $priceWithDiscount * ($item['quantity'] ?? 1),
            ];
        }, $paymentItems);
    }

    /**
     * 🔧 NUEVO: Calcular totales simples usando el monto exacto del pago
     * Para pagos DeUna, confiamos en el total que ya se pagó
     */
    private function calculateSimpleTotals(float $totalAmount): array
    {
        // Calcular componentes aproximados desde el total
        // Total = Subtotal + Shipping + IVA
        // IVA = 15% de (Subtotal + Shipping)

        // Obtener configuraciones de envío
        $shippingConfig = Configuration::where('key', 'shipping.default_cost')->first();
        $freeShippingConfig = Configuration::where('key', 'shipping.free_threshold')->first();

        $defaultShippingCost = $shippingConfig ? (float) $shippingConfig->value : 5.00;
        $freeShippingThreshold = $freeShippingConfig ? (float) $freeShippingConfig->value : 50.00;

        // Método de cálculo inverso desde el total final
        // Total = (Subtotal + Shipping) * 1.15
        // Si shipping = 0: Total = Subtotal * 1.15 -> Subtotal = Total / 1.15
        // Si shipping = 5: Total = (Subtotal + 5) * 1.15 -> Subtotal = (Total / 1.15) - 5

        $totalsWithFreeShipping = [
            'subtotal' => $totalAmount / 1.15,
            'tax' => $totalAmount - ($totalAmount / 1.15),
            'shipping' => 0,
            'total' => $totalAmount,
        ];

        $totalsWithShipping = [
            'subtotal' => ($totalAmount / 1.15) - $defaultShippingCost,
            'tax' => $totalAmount - (($totalAmount / 1.15) - $defaultShippingCost) - $defaultShippingCost,
            'shipping' => $defaultShippingCost,
            'total' => $totalAmount,
        ];

        // Decidir cuál usar basado en si el subtotal sería >= threshold
        $freeShipping = ($totalsWithFreeShipping['subtotal'] >= $freeShippingThreshold);

        $finalTotals = $freeShipping ? $totalsWithFreeShipping : $totalsWithShipping;

        // Agregar campos adicionales para compatibilidad
        $finalTotals['subtotal_original'] = $finalTotals['subtotal'];
        $finalTotals['seller_discounts'] = 0;
        $finalTotals['volume_discounts'] = 0;
        $finalTotals['total_discounts'] = 0;
        $finalTotals['free_shipping'] = $freeShipping;
        $finalTotals['free_shipping_threshold'] = $freeShippingThreshold;
        $finalTotals['tax_rate'] = $this->configService->getConfig('payment.taxRate', 15.0);
        $finalTotals['final_total'] = $finalTotals['total']; // 🔧 AGREGADO: para compatibilidad con frontend

        Log::info('💰 Totales simples calculados desde monto del pago', [
            'total_amount' => $totalAmount,
            'subtotal' => $finalTotals['subtotal'],
            'shipping' => $finalTotals['shipping'],
            'tax' => $finalTotals['tax'],
            'free_shipping' => $freeShipping,
        ]);

        return $finalTotals;
    }

    /**
     * 🔧 NUEVO: Calcular totales correctos igual que Datafast
     * Manteniendo la misma estructura y lógica
     */
    private function calculateCorrectTotals(array $items, float $totalAmount): array
    {
        $subtotalOriginal = 0;
        $subtotalWithDiscounts = 0;
        $sellerDiscounts = 0;

        // Calcular basado en los productos reales
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $product = $this->productRepository->findById($productId);
            if (! $product) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $originalPrice = $product->getPrice(); // Precio original desde DB

            // 🔧 CORREGIDO: Calcular precio con descuento del vendedor
            $sellerDiscountPercentage = $product->getDiscountPercentage(); // Obtener descuento del vendedor
            $sellerDiscountAmount = $originalPrice * ($sellerDiscountPercentage / 100);
            $priceWithSellerDiscount = $originalPrice - $sellerDiscountAmount; // Precio con descuento aplicado

            // Acumular totales
            $subtotalOriginal += $originalPrice * $quantity;
            $subtotalWithDiscounts += $priceWithSellerDiscount * $quantity; // 🔧 USAR PRECIO CON DESCUENTO
            $sellerDiscounts += $sellerDiscountAmount * $quantity; // 🔧 CALCULAR DESCUENTO CORRECTO
        }

        // Si no hay items válidos, usar cálculo simple
        if ($subtotalOriginal == 0) {
            return $this->calculateSimpleTotals($totalAmount);
        }

        // Configuraciones de envío
        $shippingConfig = Configuration::where('key', 'shipping.default_cost')->first();
        $freeShippingConfig = Configuration::where('key', 'shipping.free_threshold')->first();

        $shippingCost = $shippingConfig ? (float) $shippingConfig->value : 5.00;
        $freeShippingThreshold = $freeShippingConfig ? (float) $freeShippingConfig->value : 50.00;

        $freeShipping = $subtotalWithDiscounts >= $freeShippingThreshold;
        $finalShippingCost = $freeShipping ? 0 : $shippingCost;

        // 🔧 CORREGIDO: Estructura clara de precios con IVA dinámico
        $subtotalFinal = $subtotalWithDiscounts + $finalShippingCost; // Base gravable
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate', 15.0);
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $taxAmount = $subtotalFinal * $taxRate; // IVA dinámico sobre base gravable
        $finalTotal = $subtotalFinal + $taxAmount; // Total final

        $totals = [
            // ✅ ESTRUCTURA ESTANDARIZADA
            'subtotal' => $subtotalWithDiscounts, // Solo productos con descuentos
            'subtotal_final' => $subtotalFinal,   // Productos + envío (base gravable)
            'tax' => $taxAmount,
            'shipping' => $finalShippingCost,
            'total' => $finalTotal,
            'final_total' => $finalTotal, // 🔧 AGREGADO: para compatibilidad con frontend
            // Campos adicionales para compatibilidad
            'subtotal_original' => $subtotalOriginal,
            'seller_discounts' => $sellerDiscounts,
            'volume_discounts' => 0, // No hay descuentos por volumen en pagos simples
            'total_discounts' => $sellerDiscounts,
            'free_shipping' => $freeShipping,
            'free_shipping_threshold' => $freeShippingThreshold,
            'tax_rate' => $this->configService->getConfig('payment.taxRate', 15.0),
        ];

        Log::info('💰 Estructura de precios estandarizada', [
            'subtotal_original' => $subtotalOriginal,        // $2.00
            'seller_discounts' => $sellerDiscounts,           // $1.00
            'subtotal_with_discounts' => $subtotalWithDiscounts, // $1.00
            'shipping_cost' => $finalShippingCost,            // $5.00
            'subtotal_final' => $subtotalFinal,               // $6.00 (base gravable)
            'tax_amount' => $taxAmount,                       // $0.90
            'final_total' => $finalTotal,                     // $6.90
            'total_amount_paid' => $totalAmount,              // $6.90
            'structure_version' => 'v2_standardized',
        ]);

        return $totals;
    }

    /**
     * 🔧 NUEVO: Calcular pricing completo con todos los descuentos
     */
    private function calculatePricingWithDiscounts(array $items, float $totalAmount): array
    {
        $subtotalOriginal = 0;
        $subtotalWithSellerDiscounts = 0;
        $sellerDiscounts = 0;
        $totalQuantity = 0;

        // 1. Calcular subtotales y descuentos de vendedor
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $product = $this->productRepository->findById($productId);
            if (! $product) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $priceInItem = $item['price'] ?? 0; // Precio ya con descuento del vendedor
            $originalPrice = $product->getPrice(); // Precio original sin descuentos

            $subtotalOriginal += $originalPrice * $quantity;
            $subtotalWithSellerDiscounts += $priceInItem * $quantity;
            $sellerDiscounts += ($originalPrice - $priceInItem) * $quantity;
            $totalQuantity += $quantity;
        }

        // 2. Calcular descuentos por volumen
        $volumeDiscountPercent = $this->calculateVolumeDiscountPercentage($totalQuantity);
        $volumeDiscountAmount = $subtotalWithSellerDiscounts * ($volumeDiscountPercent / 100);
        $subtotalAfterVolume = $subtotalWithSellerDiscounts - $volumeDiscountAmount;

        // 3. Calcular envío desde configuraciones
        $shippingConfig = Configuration::where('key', 'shipping.default_cost')->first();
        $freeShippingConfig = Configuration::where('key', 'shipping.free_threshold')->first();

        $shippingCost = $shippingConfig ? (float) $shippingConfig->value : 5.00;
        $freeShippingThreshold = $freeShippingConfig ? (float) $freeShippingConfig->value : 50.00;

        $freeShipping = $subtotalAfterVolume >= $freeShippingThreshold;
        $finalShippingCost = $freeShipping ? 0 : $shippingCost;

        // 4. Calcular IVA dinámico (sobre subtotal + envío)
        $taxableAmount = $subtotalAfterVolume + $finalShippingCost;
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate', 15.0);
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $taxAmount = $taxableAmount * $taxRate; // IVA dinámico

        // 5. Total final
        $finalTotal = $subtotalAfterVolume + $finalShippingCost + $taxAmount;

        $totals = [
            'subtotal' => $subtotalAfterVolume, // Subtotal después de todos los descuentos
            'tax' => $taxAmount,
            'shipping' => $finalShippingCost,
            'total' => $finalTotal,
            // Detalles adicionales para la orden
            'subtotal_original' => $subtotalOriginal,
            'seller_discounts' => $sellerDiscounts,
            'volume_discounts' => $volumeDiscountAmount,
            'volume_discount_percentage' => $volumeDiscountPercent,
            'total_discounts' => $sellerDiscounts + $volumeDiscountAmount,
            'free_shipping' => $freeShipping,
            'free_shipping_threshold' => $freeShippingThreshold,
            'total_quantity' => $totalQuantity,
            'tax_rate' => $this->configService->getConfig('payment.taxRate', 15.0),
        ];

        Log::info('🧮 Cálculos de pricing completados', [
            'subtotal_original' => $subtotalOriginal,
            'seller_discounts' => $sellerDiscounts,
            'volume_discount_percent' => $volumeDiscountPercent,
            'volume_discount_amount' => $volumeDiscountAmount,
            'subtotal_after_discounts' => $subtotalAfterVolume,
            'shipping_cost' => $finalShippingCost,
            'tax_amount' => $taxAmount,
            'final_total' => $finalTotal,
            'total_quantity' => $totalQuantity,
        ]);

        return $totals;
    }

    /**
     * 🔧 NUEVO: Calcular porcentaje de descuento por volumen basado en configuraciones
     */
    private function calculateVolumeDiscountPercentage(int $totalQuantity): float
    {
        // Obtener configuración de descuentos por volumen
        $volumeDiscountConfig = Configuration::where('key', 'volume_discounts.default_tiers')->first();

        if (! $volumeDiscountConfig) {
            // Descuentos por defecto si no hay configuración
            $tiers = [
                ['quantity' => 3, 'discount' => 5],
                ['quantity' => 6, 'discount' => 10],
                ['quantity' => 12, 'discount' => 15],
            ];
        } else {
            $tiers = json_decode($volumeDiscountConfig->value, true);
        }

        // Encontrar el descuento aplicable (el mayor que califique)
        $applicableDiscount = 0;
        foreach ($tiers as $tier) {
            if ($totalQuantity >= $tier['quantity']) {
                $applicableDiscount = $tier['discount'];
            }
        }

        Log::info('🎯 Descuento por volumen calculado', [
            'total_quantity' => $totalQuantity,
            'applicable_discount' => $applicableDiscount,
            'tiers_checked' => $tiers,
        ]);

        return (float) $applicableDiscount;
    }

    /**
     * 🔧 NUEVO: Usar PricingCalculatorService centralizado para calcular totales
     * Garantiza consistencia con Datafast y todos los demás flujos
     */
    private function calculateTotalsUsingCentralizedService(array $items, int $userId, float $paidAmount): array
    {
        try {
            Log::info('🧮 DEUNA WEBHOOK: Usando PricingCalculatorService centralizado', [
                'items_count' => count($items),
                'user_id' => $userId,
                'paid_amount' => $paidAmount,
            ]);

            // Preparar items para el servicio centralizado
            $pricingItems = [];
            foreach ($items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $pricingItems[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'] ?? 1,
                    ];
                }
            }

            if (empty($pricingItems)) {
                Log::warning('⚠️ No hay items válidos para pricing centralizado, usando cálculo simple');
                return $this->calculateSimpleTotals($paidAmount);
            }

            // Usar servicio centralizado (sin cupón para webhooks)
            $pricingResult = $this->pricingService->calculateCartTotals($pricingItems, $userId, null);

            // Convertir a formato compatible con la base de datos
            $totals = [
                'subtotal' => $pricingResult['subtotal_with_discounts'],
                'subtotal_final' => $pricingResult['subtotal_after_coupon'] + $pricingResult['shipping_cost'],
                'tax' => $pricingResult['iva_amount'],
                'shipping' => $pricingResult['shipping_cost'],
                'total' => $pricingResult['final_total'],
                'final_total' => $pricingResult['final_total'],
                // Campos adicionales para la base de datos
                'subtotal_original' => $pricingResult['subtotal_original'],
                'seller_discounts' => $pricingResult['seller_discounts'],
                'volume_discounts' => $pricingResult['volume_discounts'],
                'total_discounts' => $pricingResult['total_discounts'],
                'free_shipping' => $pricingResult['free_shipping'],
                'free_shipping_threshold' => $pricingResult['free_shipping_threshold'],
                'tax_rate' => $this->configService->getConfig('payment.taxRate', 15.0),
            ];

            Log::info('✅ DEUNA WEBHOOK: Totales calculados con PricingCalculatorService', [
                'subtotal_original' => $totals['subtotal_original'],
                'seller_discounts' => $totals['seller_discounts'],
                'volume_discounts' => $totals['volume_discounts'],
                'total_discounts' => $totals['total_discounts'],
                'final_total' => $totals['final_total'],
                'paid_amount' => $paidAmount,
                'difference' => abs($totals['final_total'] - $paidAmount),
            ]);

            return $totals;

        } catch (\Exception $e) {
            Log::error('❌ Error usando PricingCalculatorService en webhook, fallback a cálculo simple', [
                'error' => $e->getMessage(),
                'items' => $items,
                'user_id' => $userId,
                'paid_amount' => $paidAmount,
            ]);

            // Fallback a cálculo simple si hay error
            return $this->calculateSimpleTotals($paidAmount);
        }
    }

    /**
     * 🔧 NUEVO: Reducir stock de productos después del pago
     */
    private function reduceProductStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 1;

            if (! $productId) {
                Log::warning('⚠️ No se puede reducir stock: product_id faltante', ['item' => $item]);

                continue;
            }

            try {
                $product = $this->productRepository->findById($productId);
                if (! $product) {
                    Log::error('❌ Producto no encontrado para reducir stock', ['product_id' => $productId]);

                    continue;
                }

                $stockAnterior = $product->getStock();

                // Reducir stock usando el repositorio
                $this->productRepository->updateStock($productId, $quantity, 'decrease');

                Log::info('📦 Stock reducido exitosamente', [
                    'product_id' => $productId,
                    'product_name' => $product->getName(),
                    'stock_anterior' => $stockAnterior,
                    'cantidad_reducida' => $quantity,
                    'stock_nuevo' => $stockAnterior - $quantity,
                ]);

            } catch (Exception $e) {
                Log::error('❌ Error reduciendo stock del producto', [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage(),
                ]);
                // No fallar el webhook por errores de stock
            }
        }
    }

    /**
     * ✅ NUEVO: Crear seller_orders para pagos de Deuna (igual que Datafast)
     * Este método asegura que los pedidos pagados con Deuna aparezcan en la tabla del seller
     */
    private function createSellerOrdersForDeuna($order): void
    {
        try {
            // Obtener los order_items de la orden
            $orderItems = \App\Models\OrderItem::where('order_id', $order->getId())->get();
            
            if ($orderItems->isEmpty()) {
                Log::warning('No order items found for Deuna order', ['order_id' => $order->getId()]);
                return;
            }

            // Agrupar items por seller
            $itemsBySeller = [];
            foreach ($orderItems as $item) {
                $sellerId = $item->seller_id;
                
                // ✅ LOG: Debug seller_id agrupamiento
                Log::info('🔍 DEUNA: Agrupando item por seller_id', [
                    'order_id' => $order->getId(),
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'seller_id' => $sellerId,
                    'subtotal' => $item->subtotal
                ]);
                
                if (!isset($itemsBySeller[$sellerId])) {
                    $itemsBySeller[$sellerId] = [];
                }
                $itemsBySeller[$sellerId][] = $item;
            }
            
            Log::info('✅ DEUNA: Items agrupados por seller', [
                'order_id' => $order->getId(),
                'sellers_count' => count($itemsBySeller),
                'sellers' => array_keys($itemsBySeller)
            ]);

            // Crear un seller_order para cada seller
            foreach ($itemsBySeller as $sellerId => $items) {
                // Calcular totales para este seller
                $sellerTotal = 0;
                $originalTotal = 0;
                
                foreach ($items as $item) {
                    $sellerTotal += $item->subtotal; // ✅ CORRECCIÓN: usar subtotal (price * quantity)
                    $originalTotal += $item->original_price * $item->quantity; // ✅ CORRECCIÓN: multiplicar por cantidad
                }

                // Verificar si ya existe un seller_order para evitar duplicados
                $existingSellerOrder = \App\Models\SellerOrder::where('order_id', $order->getId())
                    ->where('seller_id', $sellerId)
                    ->first();
                    
                if ($existingSellerOrder) {
                    Log::info('Seller order already exists for Deuna order', [
                        'order_id' => $order->getId(),
                        'seller_id' => $sellerId,
                        'seller_order_id' => $existingSellerOrder->id
                    ]);
                    continue;
                }

                // Crear el seller_order
                $sellerOrder = \App\Models\SellerOrder::create([
                    'order_id' => $order->getId(),
                    'seller_id' => $sellerId,
                    'order_number' => $order->getOrderNumber() . '-S' . $sellerId,
                    'status' => 'processing', // Estado inicial
                    'total' => $sellerTotal,
                    'original_total' => $originalTotal,
                    'subtotal_products' => $sellerTotal,
                    'shipping_cost' => 0, // Se calculará después si aplica
                    'total_discounts' => $originalTotal - $sellerTotal,
                    'payment_status' => 'completed', // Ya está pagado con Deuna
                    'payment_method' => 'deuna',
                    'shipping_data' => $order->getShippingData(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ✅ CRÍTICO: Actualizar seller_order_id en la tabla orders
                \App\Models\Order::where('id', $order->getId())
                    ->update(['seller_order_id' => $sellerOrder->id]);

                // ✅ CRÍTICO: Actualizar seller_order_id en los OrderItems de este seller
                \App\Models\OrderItem::where('order_id', $order->getId())
                    ->where('seller_id', $sellerId)
                    ->update(['seller_order_id' => $sellerOrder->id]);

                $updatedItemsCount = \App\Models\OrderItem::where('order_id', $order->getId())
                    ->where('seller_id', $sellerId)
                    ->count();

                // ✅ CRÍTICO: Crear registro de Shipping para el SellerOrder
                $this->createShippingRecord($sellerOrder->id, $order);

                Log::info('✅ Seller order created successfully for Deuna payment', [
                    'seller_order_id' => $sellerOrder->id,
                    'order_id' => $order->getId(),
                    'seller_id' => $sellerId,
                    'order_number' => $sellerOrder->order_number,
                    'total' => $sellerTotal,
                    'original_total' => $originalTotal,
                    'total_discounts' => $originalTotal - $sellerTotal,
                    'items_count' => count($items),
                    'payment_status' => $sellerOrder->payment_status,
                    'payment_method' => $sellerOrder->payment_method,
                    'order_updated' => 'seller_order_id set',
                    'order_items_updated' => $updatedItemsCount,
                    'shipping_record_created' => true
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error creating seller orders for Deuna', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para no fallar el webhook
        }
    }

    /**
     * ✅ NUEVO: Crear registro de Shipping para un SellerOrder
     */
    private function createShippingRecord(int $sellerOrderId, $order): void
    {
        try {
            // Verificar si ya existe un shipping para este seller_order
            $existingShipping = \App\Models\Shipping::where('seller_order_id', $sellerOrderId)->first();
            if ($existingShipping) {
                Log::info('Shipping record already exists for seller order', [
                    'seller_order_id' => $sellerOrderId,
                    'shipping_id' => $existingShipping->id
                ]);
                return;
            }

            // Generar número de tracking
            $trackingNumber = \App\Models\Shipping::generateTrackingNumber();
            
            // Obtener datos de envío de la orden
            $shippingData = $order->getShippingData();
            $currentLocation = null;
            
            if ($shippingData && is_array($shippingData)) {
                $currentLocation = [
                    'address' => $shippingData['address'] ?? '',
                    'city' => $shippingData['city'] ?? '',
                    'state' => $shippingData['state'] ?? '',
                    'country' => $shippingData['country'] ?? 'Ecuador',
                    'postal_code' => $shippingData['postal_code'] ?? ''
                ];
            }

            // Crear registro de Shipping
            $shipping = \App\Models\Shipping::create([
                'seller_order_id' => $sellerOrderId,
                'tracking_number' => $trackingNumber,
                'status' => 'processing',
                'current_location' => $currentLocation,
                'estimated_delivery' => now()->addDays(3), // 3 días por defecto
                'carrier_name' => 'Courier Local',
                'last_updated' => now()
            ]);

            // Crear evento inicial en el historial
            $shipping->addHistoryEvent(
                'processing',
                $currentLocation,
                'Pedido recibido y en proceso de preparación',
                now()
            );

            Log::info('✅ Shipping record created for seller order', [
                'seller_order_id' => $sellerOrderId,
                'shipping_id' => $shipping->id,
                'tracking_number' => $trackingNumber,
                'status' => 'processing'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating shipping record for seller order', [
                'seller_order_id' => $sellerOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para no fallar el proceso principal
        }
    }
}
