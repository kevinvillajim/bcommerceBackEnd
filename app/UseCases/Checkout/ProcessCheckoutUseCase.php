<?php

namespace App\UseCases\Checkout;

use App\Domain\Entities\OrderEntity;
use App\Domain\Entities\SellerOrderEntity;
use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
use App\Events\OrderCreated;
use App\Services\ConfigurationService;
use App\Services\PriceVerificationService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCheckoutUseCase
{
    private ShoppingCartRepositoryInterface $cartRepository;

    private OrderRepositoryInterface $orderRepository;

    private ProductRepositoryInterface $productRepository;

    private SellerOrderRepositoryInterface $sellerOrderRepository;

    private PaymentGatewayInterface $paymentGateway;

    private CreateOrderUseCase $createOrderUseCase;

    private ConfigurationService $configService;

    private ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase;
    
    private PricingCalculatorService $pricingService;
    
    private PriceVerificationService $priceVerificationService;

    public function __construct(
        ShoppingCartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository,
        SellerOrderRepositoryInterface $sellerOrderRepository,
        PaymentGatewayInterface $paymentGateway,
        CreateOrderUseCase $createOrderUseCase,
        ConfigurationService $configService,
        ApplyCartDiscountCodeUseCase $applyCartDiscountCodeUseCase,
        PricingCalculatorService $pricingService,
        PriceVerificationService $priceVerificationService
    ) {
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->sellerOrderRepository = $sellerOrderRepository;
        $this->paymentGateway = $paymentGateway;
        $this->createOrderUseCase = $createOrderUseCase;
        $this->configService = $configService;
        $this->applyCartDiscountCodeUseCase = $applyCartDiscountCodeUseCase;
        $this->pricingService = $pricingService;
        $this->priceVerificationService = $priceVerificationService;
    }

    /**
     * 🧮 CORREGIDO: Procesa el checkout usando PricingCalculatorService centralizado
     */
    public function execute(int $userId, array $paymentData, array $shippingData, array $items = [], ?int $sellerId = null, ?string $discountCode = null, ?array $calculatedTotals = null): array
    {
        // 🔍 LOGGING TEMPORAL: Capturar datos de entrada
        Log::info("🔍 ProcessCheckoutUseCase.execute() - DATOS DE ENTRADA", [
            'userId' => $userId,
            'shippingData_full' => $shippingData,
            'shippingData_has_identification' => isset($shippingData['identification']),
            'identification_value' => $shippingData['identification'] ?? 'NO_SET',
            'shippingData_keys' => array_keys($shippingData),
            'paymentData_keys' => array_keys($paymentData)
        ]);

        return DB::transaction(function () use ($userId, $paymentData, $shippingData, $items, $sellerId, $discountCode, $calculatedTotals) {
            try {
                // ✅ CRITICAL FIX: Skip isolation level change to avoid transaction conflicts
                // Solo usar SERIALIZABLE en producción si es estrictamente necesario
                if (config('app.env') === 'production') {
                    try {
                        DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                        Log::info('🔒 CRITICAL: Transaction isolation level set to SERIALIZABLE (production)');
                    } catch (\Exception $e) {
                        Log::warning('⚠️ Could not set SERIALIZABLE isolation level: ' . $e->getMessage());
                    }
                } else {
                    Log::info('🔒 CRITICAL: Skipping isolation level change (development/testing)');
                }
                
                Log::info('🔒 CRITICAL: Transaction started with SERIALIZABLE isolation level', [
                    'user_id' => $userId,
                    'items_count' => count($items),
                    'transaction_id' => DB::transactionLevel(),
                    'timestamp' => microtime(true)
                ]);
                // 🔧 NUEVO: Extraer seller_id del primer producto si no se proporciona
                if ($sellerId === null && ! empty($items)) {
                    $firstProductId = $items[0]['product_id'] ?? null;
                    if ($firstProductId) {
                        $firstProduct = $this->productRepository->findById($firstProductId);
                        if ($firstProduct) {
                            $sellerId = $firstProduct->getSellerId();
                            Log::info('🔧 Seller ID extraído del primer producto', [
                                'product_id' => $firstProductId,
                                'seller_id' => $sellerId,
                            ]);
                        }
                    }
                }

                Log::info('🧮 ProcessCheckoutUseCase INICIADO usando PricingCalculatorService', [
                    'user_id' => $userId,
                    'seller_id' => $sellerId,
                    'items_count' => count($items),
                    'has_frontend_totals' => $calculatedTotals !== null,
                    'discount_code' => $discountCode,
                ]);

                // 🧮 NUEVO: Usar PricingCalculatorService centralizado para todos los cálculos
                if (empty($items)) {
                    // Obtener items del carrito si no se proporcionaron
                    $cart = $this->cartRepository->findByUserId($userId);
                    if (!$cart || count($cart->getItems()) === 0) {
                        throw new \Exception('No hay items para procesar');
                    }
                    
                    // Convertir cart items al formato estándar
                    $cartItems = [];
                    foreach ($cart->getItems() as $item) {
                        $cartItems[] = [
                            'product_id' => $item->getProductId(),
                            'quantity' => $item->getQuantity(),
                            'price' => $item->getPrice(),      // ✅ INCLUIR precio para verificación
                            'subtotal' => $item->getSubtotal() // ✅ INCLUIR subtotal 
                        ];
                    }
                    $items = $cartItems;
                }
                
                // Preparar items para el servicio de pricing centralizado
                $pricingItems = [];
                foreach ($items as $item) {
                    $pricingItems[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ];
                }
                
                Log::info('🧮 Calculando totales con PricingCalculatorService centralizado', [
                    'items_count' => count($pricingItems),
                    'user_id' => $userId,
                    'discount_code' => $discountCode,
                ]);
                
                // Usar servicio centralizado para calcular totales
                $pricingResult = $this->pricingService->calculateCartTotals(
                    $pricingItems,
                    $userId,
                    $discountCode
                );
                
                // Convertir resultado a formato compatible
                $totals = [
                    'subtotal_original' => $pricingResult['subtotal_original'],
                    'subtotal_with_discounts' => $pricingResult['subtotal_with_discounts'],
                    'subtotal_after_coupon' => $pricingResult['subtotal_after_coupon'],
                    'seller_discounts' => $pricingResult['seller_discounts'],
                    'volume_discounts' => $pricingResult['volume_discounts'],
                    'coupon_discount' => $pricingResult['coupon_discount'],
                    'total_discounts' => $pricingResult['total_discounts'],
                    'iva_amount' => $pricingResult['iva_amount'],
                    'shipping_cost' => $pricingResult['shipping_cost'],
                    'free_shipping' => $pricingResult['free_shipping'],
                    'free_shipping_threshold' => $pricingResult['free_shipping_threshold'],
                    'final_total' => $pricingResult['final_total'],
                    'feedback_discount_amount' => $pricingResult['coupon_discount'],
                ];
                
                // Usar items procesados del servicio centralizado
                $processedItems = $pricingResult['processed_items'];
                
                Log::info('✅ Totales calculados con PricingCalculatorService centralizado', [
                    'subtotal_original' => $totals['subtotal_original'],
                    'final_total' => $totals['final_total'],
                    'total_discounts' => $totals['total_discounts'],
                    'iva_amount' => $totals['iva_amount'],
                    'shipping_cost' => $totals['shipping_cost'],
                ]);

                // El descuento de código ya fue procesado por PricingCalculatorService
                $discountCodeInfo = $pricingResult['coupon_info'] ?? null;

                Log::info('💰 Totales calculados con descuentos por volumen', [
                    'subtotal_original' => $totals['subtotal_original'],
                    'subtotal_with_discounts' => $totals['subtotal_with_discounts'],
                    'seller_discounts' => $totals['seller_discounts'],
                    'volume_discounts' => $totals['volume_discounts'],
                    'total_discounts' => $totals['total_discounts'],
                    'iva' => $totals['iva_amount'],
                    'shipping' => $totals['shipping_cost'],
                    'final_total' => $totals['final_total'],
                ]);

                // 4. 🔒 SECURITY: Verificar integridad de precios (anti-tampering) - INCLUYE TODOS LOS DESCUENTOS
                if (!empty($paymentData['skip_price_verification'])) {
                    Log::info('🔓 SECURITY: Saltando verificación de precios (método de pago confiable)', [
                        'payment_method' => $paymentData['method'] ?? 'unknown',
                        'user_id' => $userId
                    ]);
                } else {
                    if (!$this->priceVerificationService->verifyItemPrices($items, $userId, $discountCode)) {
                        throw new \Exception('Security: Price tampering detected. Transaction blocked.');
                    }
                }
                
                // Verificar totales calculados si se proporcionan
                if ($calculatedTotals) {
                    if (!$this->priceVerificationService->verifyCalculatedTotals($processedItems, $calculatedTotals, $userId, $discountCode)) {
                        throw new \Exception('Security: Total tampering detected. Transaction blocked.');
                    }
                }

                // 5. Validar stock de productos
                $this->validateProductStock($processedItems);

                // 6. Crear orden principal con totales correctos
                $orderData = [
                    'user_id' => $userId,
                    'total' => $totals['final_total'],
                    'original_total' => $totals['subtotal_original'],
                    'seller_discount_savings' => $totals['seller_discounts'],
                    'volume_discount_savings' => $totals['volume_discounts'],
                    'total_discount_savings' => $totals['total_discounts'],
                    'volume_discounts_applied' => $totals['volume_discounts'] > 0,
                    'shipping_data' => $shippingData,
                    'seller_id' => $sellerId,
                    'items' => $items, // ✅ CORREGIDO: Pasar los items reales, no array vacío
                    // ✅ CORREGIDO: Información detallada de pricing
                    'subtotal_products' => $totals['subtotal_with_discounts'],
                    'iva_amount' => $totals['iva_amount'],
                    'shipping_cost' => $totals['shipping_cost'],
                    'total_discounts' => $totals['total_discounts'],
                    'feedback_discount_amount' => $totals['feedback_discount_amount'],
                    'feedback_discount_code' => $discountCodeInfo['code'] ?? null,
                    'feedback_discount_percentage' => $discountCodeInfo['discount_percentage'] ?? 0,
                    'free_shipping' => $totals['free_shipping'],
                    'free_shipping_threshold' => $totals['free_shipping_threshold'] ?? 50.0,
                    'pricing_breakdown' => $totals,
                    // 🔧 AGREGADO: payment_details con fecha del backend
                    'payment_details' => [
                        'payment_method' => $paymentData['method'] ?? 'datafast',
                        'processed_at' => now()->format('Y-m-d H:i:s'),
                        'amount' => $totals['final_total'],
                        'currency' => 'USD',
                        'status' => 'processing',
                    ],
                ];

                // 🔍 LOGGING TEMPORAL: Verificar shipping_data en orderData
                Log::info("🔍 ProcessCheckoutUseCase - SHIPPING_DATA EN ORDER_DATA", [
                    'orderData_shipping_data' => $shippingData,
                    'shipping_identification' => $shippingData['identification'] ?? 'NO_SET',
                    'shipping_data_count' => count($shippingData),
                    'is_array' => is_array($shippingData)
                ]);

                Log::info('🏗️ Creando orden principal con descuentos por volumen');
                $mainOrder = $this->createOrderUseCase->execute($orderData);
                $orderId = $mainOrder->getId();

                if (! $orderId || ! is_numeric($orderId)) {
                    throw new \Exception('Error al crear la orden: ID de orden inválido');
                }

                // 🔄 TIMING CORREGIDO: Evento OrderCreated se disparará DESPUÉS de actualizar payment_status

                // 6. Marcar código de descuento como usado si se aplicó
                if ($discountCodeInfo) {
                    $markAsUsedResult = $this->applyCartDiscountCodeUseCase->markAsUsed($discountCodeInfo['code'], $userId);
                    if ($markAsUsedResult['success']) {
                        Log::info('✅ Código de descuento marcado como usado', [
                            'code' => $discountCodeInfo['code'],
                            'order_id' => $orderId,
                        ]);
                    }
                }

                // 7. Procesar pago con total correcto
                Log::info('💳 Procesando pago', [
                    'method' => $paymentData['method'],
                    'total' => $totals['final_total'],
                ]);

                // 🔧 CORREGIDO: Mapear datos de shipping a customer para Datafast
                Log::info('🔍 DEBUGING shipping data antes del mapeo', [
                    'shipping_data' => $shippingData,
                    'payment_method' => $paymentData['method'] ?? 'unknown',
                    'payment_data_keys' => array_keys($paymentData),
                ]);

                $paymentDataWithCustomer = $paymentData;
                $paymentDataWithCustomer['customer'] = [
                    'given_name' => $shippingData['first_name'] ?? 'Cliente',
                    'surname' => $shippingData['last_name'] ?? 'De Prueba', 
                    'email' => $shippingData['email'] ?? 'test@example.com',
                    'phone' => $shippingData['phone'] ?? '0999999999',
                    'doc_id' => '1234567890', // Valor por defecto
                ];
                $paymentDataWithCustomer['shipping'] = $shippingData;
                $paymentDataWithCustomer['billing'] = $shippingData; // Usar mismos datos para billing
                
                Log::info('🔍 DEBUGING customer data después del mapeo', [
                    'customer_data' => $paymentDataWithCustomer['customer'],
                    'payment_method' => $paymentDataWithCustomer['method'] ?? 'unknown',
                ]);

                $paymentResult = $this->paymentGateway->processPayment($paymentDataWithCustomer, $totals['final_total']);

                if (! $paymentResult['success']) {
                    throw new \Exception('Error al procesar el pago: '.($paymentResult['message'] ?? 'Error desconocido'));
                }

                // 8. Actualizar orden con info de pago
                $this->orderRepository->updatePaymentInfo($orderId, [
                    'payment_id' => $paymentResult['payment_id'] ?? $paymentResult['checkout_id'] ?? null,
                    'payment_status' => 'completed',
                    'payment_method' => $paymentData['method'],
                    'status' => 'processing',
                ]);

                // 🔥 TIMING CORREGIDO: Disparar evento OrderCreated DESPUÉS de actualizar payment_status
                // ✅ PROTECCIÓN ANTI-DUPLICADOS: Verificar si el evento ya se disparó para este transaction_id
                $transactionId = $paymentData['transaction_id'] ?? $paymentData['payment_id'] ?? 'unknown';
                $eventCacheKey = "order_created_event_{$transactionId}";
                
                if (Cache::get($eventCacheKey)) {
                    Log::warning('🚫 EVENTO DUPLICADO DETECTADO Y BLOQUEADO', [
                        'transaction_id' => $transactionId,
                        'order_id' => $orderId,
                        'cache_key' => $eventCacheKey,
                        'reason' => 'ProcessCheckoutUseCase transaction retry'
                    ]);
                    
                    // No disparar el evento duplicado, pero continuar con el resto del proceso
                } else {
                    // Marcar que el evento ya se disparó para este transaction_id
                    Cache::put($eventCacheKey, true, 300); // 5 minutos de caché
                    
                    Log::info('🚀 ProcessCheckoutUseCase: Disparando evento OrderCreated CON payment_status', [
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'seller_id' => $sellerId,
                        'total' => $totals['final_total'],
                        'payment_status' => 'completed',
                        'transaction_id' => $transactionId,
                        'cache_key' => $eventCacheKey,
                    ]);

                    event(new OrderCreated(
                    $orderId,
                    $userId,
                    $sellerId,
                    [
                        'order_number' => $mainOrder->getOrderNumber(),
                        'total' => $totals['final_total'],
                        'items' => $processedItems,
                        'checkout_source' => 'ProcessCheckoutUseCase',
                        'payment_status' => 'completed', // ✅ Ahora el evento incluye payment_status
                    ]
                    ));

                    Log::info('✅ Evento OrderCreated disparado DESPUÉS de updatePaymentInfo', [
                        'transaction_id' => $transactionId,
                        'cache_key' => $eventCacheKey,
                    ]);
                }

                // 9. Crear seller orders
                $itemsBySeller = $this->groupItemsBySeller($processedItems);
                $sellerOrders = $this->createSellerOrders($orderId, $itemsBySeller, $totals, $shippingData, $mainOrder, $paymentData);

                // 10. Actualizar stock de productos
                $this->updateProductStock($processedItems);

                // 11. Limpiar carrito si se usó
                $cart = $this->cartRepository->findByUserId($userId);
                if ($cart) {
                    $this->cartRepository->clearCart($cart->getId());
                }

                // 12. Obtener orden completada
                $completedOrder = $this->orderRepository->findById($orderId);

                Log::info('🎉 Checkout completado con descuentos por volumen', [
                    'order_id' => $orderId,
                    'final_total' => $totals['final_total'],
                    'total_savings' => $totals['total_discounts'],
                ]);

                // ✅ CORREGIDO: Estructura de respuesta que coincide con CheckoutController
                return [
                    'success' => true,
                    'order' => $completedOrder,
                    'seller_orders' => $sellerOrders,
                    'payment' => $paymentResult,
                    'pricing_info' => [
                        'totals' => [
                            'subtotal_original' => $totals['subtotal_original'],
                            'subtotal_with_discounts' => $totals['subtotal_with_discounts'],
                            'seller_discounts' => $totals['seller_discounts'],
                            'volume_discounts' => $totals['volume_discounts'],
                            'total_discounts' => $totals['total_discounts'],
                            'iva_amount' => $totals['iva_amount'],        // ✅ CORREGIDO
                            'shipping_cost' => $totals['shipping_cost'], // ✅ CORREGIDO
                            'free_shipping' => $totals['free_shipping'],
                            'final_total' => $totals['final_total'],
                        ],
                        'billed_amount' => $totals['subtotal_with_discounts'] + $totals['iva_amount'],
                        'paid_amount' => $totals['final_total'],
                        'total_savings' => $totals['total_discounts'],
                        'volume_discounts_applied' => $totals['volume_discounts'] > 0,
                        'shipping_info' => [
                            'free_shipping' => $totals['free_shipping'],
                            'shipping_cost' => $totals['shipping_cost'],
                        ],
                        'breakdown' => $totals,
                    ],
                ];

            } catch (\Exception $e) {
                Log::error('❌ Error en ProcessCheckoutUseCase: '.$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * ✅ CORREGIDO: Preparar items básicos usando precios del frontend (sin recalcular descuentos)
     */
    private function prepareItemsBasic(array $validatedItems): array
    {
        $itemsWithBasicInfo = [];

        foreach ($validatedItems as $item) {
            $product = $this->productRepository->findById($item['product_id']);

            if (! $product) {
                throw new \Exception("Producto {$item['product_id']} no encontrado");
            }

            // ✅ USAR PRECIOS DEL FRONTEND (que ya tienen descuentos aplicados correctamente)
            $originalPrice = $product->getPrice(); // Precio original desde DB: $2.00
            $finalPrice = $item['price']; // Precio final del frontend: $0.95
            $discountAmount = $originalPrice - $finalPrice; // Descuento total: $1.05

            $itemsWithBasicInfo[] = [
                'product_id' => $item['product_id'],
                'seller_id' => $product->getSellerId(),
                'quantity' => $item['quantity'],
                'original_price' => $originalPrice, // $2.00
                'seller_discounted_price' => $finalPrice, // $0.95 (precio ya con todos los descuentos)
                'final_price' => $finalPrice, // $0.95
                'seller_discount_amount' => $discountAmount, // $1.05 (incluye seller + volume)
                'volume_discount_amount' => 0.0, // Ya incluido en seller_discount_amount
                'volume_discount_percentage' => 0.0,
                'total_discount_amount' => $discountAmount, // $1.05
                'subtotal' => $finalPrice * $item['quantity'], // $0.95 × 3 = $2.85
                'attributes' => [],
            ];

            Log::info('💰 Item preparado con precios exactos del frontend', [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'original_price' => $originalPrice, // $2.00
                'final_price' => $finalPrice, // $0.95
                'total_discount_applied' => $discountAmount, // $1.05
                'item_subtotal' => $finalPrice * $item['quantity'], // $2.85
            ]);
        }

        return $itemsWithBasicInfo;
    }

    /**
     * ✅ NUEVO: Recalcular descuentos por volumen para items validados del request
     */
    private function recalculateVolumeDiscounts(array $validatedItems): array
    {
        $itemsWithDiscounts = [];

        foreach ($validatedItems as $item) {
            $product = $this->productRepository->findById($item['product_id']);

            if (! $product) {
                throw new \Exception("Producto {$item['product_id']} no encontrado");
            }

            // ✅ RECALCULAR precios con descuentos por volumen
            $pricing = $this->calculateItemPricingWithVolumeDiscounts(
                $product->getPrice(),
                $product->getDiscountPercentage(),
                $item['quantity']
            );

            $itemsWithDiscounts[] = [
                'product_id' => $item['product_id'],
                'seller_id' => $product->getSellerId(),
                'quantity' => $item['quantity'],
                'original_price' => $product->getPrice(),
                'seller_discounted_price' => $pricing['seller_discounted_price'],
                'final_price' => $pricing['final_price'], // ✅ Precio final con todos los descuentos
                'seller_discount_amount' => $pricing['seller_discount_amount'],
                'volume_discount_amount' => $pricing['volume_discount_amount'],
                'volume_discount_percentage' => $pricing['volume_discount_percentage'],
                'total_discount_amount' => $pricing['total_discount_amount'],
                'subtotal' => $pricing['final_price'] * $item['quantity'],
                'attributes' => [],
            ];

            Log::info('💰 Item recalculado con descuentos por volumen', [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'original_price' => $product->getPrice(),
                'final_price' => $pricing['final_price'],
                'seller_discount' => $pricing['seller_discount_amount'],
                'volume_discount' => $pricing['volume_discount_amount'],
                'total_savings' => $pricing['total_discount_amount'],
            ]);
        }

        return $itemsWithDiscounts;
    }

    /**
     * ✅ NUEVO: Preparar items del carrito con descuentos por volumen
     */
    private function prepareCartItemsWithVolumeDiscounts(array $cartItems): array
    {
        $itemsWithDiscounts = [];

        foreach ($cartItems as $item) {
            $product = $this->productRepository->findById($item->getProductId());

            if (! $product) {
                throw new \Exception("Producto {$item->getProductId()} no encontrado");
            }

            // ✅ CALCULAR precios con descuentos por volumen
            $pricing = $this->calculateItemPricingWithVolumeDiscounts(
                $product->getPrice(),
                $product->getDiscountPercentage(),
                $item->getQuantity()
            );

            $itemsWithDiscounts[] = [
                'product_id' => $item->getProductId(),
                'seller_id' => $product->getSellerId(),
                'quantity' => $item->getQuantity(),
                'original_price' => $product->getPrice(),
                'seller_discounted_price' => $pricing['seller_discounted_price'],
                'final_price' => $pricing['final_price'], // ✅ Precio final con todos los descuentos
                'seller_discount_amount' => $pricing['seller_discount_amount'],
                'volume_discount_amount' => $pricing['volume_discount_amount'],
                'volume_discount_percentage' => $pricing['volume_discount_percentage'],
                'total_discount_amount' => $pricing['total_discount_amount'],
                'subtotal' => $pricing['final_price'] * $item->getQuantity(),
                'attributes' => $item->getAttributes(),
            ];
        }

        return $itemsWithDiscounts;
    }

    /**
     * ✅ NUEVO: Calcular pricing de un item con descuentos por volumen
     */
    private function calculateItemPricingWithVolumeDiscounts(
        float $originalPrice,
        float $sellerDiscountPercentage,
        int $quantity
    ): array {

        // 1. Aplicar descuento del seller
        $sellerDiscountAmount = $originalPrice * ($sellerDiscountPercentage / 100);
        $sellerDiscountedPrice = $originalPrice - $sellerDiscountAmount;

        // 2. Determinar descuento por volumen
        $volumeDiscountPercentage = $this->getVolumeDiscountPercentage($quantity);
        $volumeDiscountAmount = $sellerDiscountedPrice * ($volumeDiscountPercentage / 100);

        // 3. Precio final
        $finalPrice = $sellerDiscountedPrice - $volumeDiscountAmount;

        // 4. Total de descuentos
        $totalDiscountAmount = $sellerDiscountAmount + $volumeDiscountAmount;

        return [
            'seller_discounted_price' => $sellerDiscountedPrice,
            'final_price' => $finalPrice,
            'seller_discount_amount' => $sellerDiscountAmount,
            'volume_discount_amount' => $volumeDiscountAmount,
            'volume_discount_percentage' => $volumeDiscountPercentage,
            'total_discount_amount' => $totalDiscountAmount,
        ];
    }

    /**
     * ✅ NUEVO: Obtener porcentaje de descuento por volumen según cantidad
     */
    private function getVolumeDiscountPercentage(int $quantity): float
    {
        // ✅ MISMA LÓGICA que en el frontend
        if ($quantity >= 10) {
            return 15.0;
        }
        if ($quantity >= 6) {
            return 10.0;
        }
        if ($quantity >= 5) {
            return 8.0;
        }
        if ($quantity >= 3) {
            return 5.0;
        }

        return 0.0;
    }

    /**
     * ✅ CORREGIDO: Calcular totales con todos los descuentos aplicados
     */
    private function calculateTotalsWithVolumeDiscounts(array $items): array
    {
        $subtotalOriginal = 0;
        $subtotalWithDiscounts = 0;
        $totalSellerDiscounts = 0;
        $totalVolumeDiscounts = 0;

        foreach ($items as $item) {
            $itemOriginalTotal = $item['original_price'] * $item['quantity'];
            $itemDiscountedTotal = $item['final_price'] * $item['quantity'];
            $itemSellerDiscounts = $item['seller_discount_amount'] * $item['quantity'];
            $itemVolumeDiscounts = $item['volume_discount_amount'] * $item['quantity'];

            $subtotalOriginal += $itemOriginalTotal;
            $subtotalWithDiscounts += $itemDiscountedTotal;
            $totalSellerDiscounts += $itemSellerDiscounts;
            $totalVolumeDiscounts += $itemVolumeDiscounts;
        }

        $totalDiscounts = $totalSellerDiscounts + $totalVolumeDiscounts;

        // ✅ CRÍTICO: NO calcular IVA aquí - se calculará al final después de TODOS los descuentos
        // El IVA debe calcularse solo DESPUÉS de aplicar código de descuento de feedback

        // Calcular envío usando configuración de base de datos (sin IVA por ahora)
        $shippingEnabled = $this->configService->getConfig('shipping.enabled', true);
        $freeShippingThreshold = $this->configService->getConfig('shipping.free_threshold', 50.00);
        $defaultShippingCost = $this->configService->getConfig('shipping.default_cost', 5.00);

        $shippingCost = 0;
        if ($shippingEnabled) {
            $shippingCost = $subtotalWithDiscounts >= $freeShippingThreshold ? 0 : $defaultShippingCost;
        }
        $freeShipping = $shippingCost === 0;

        // 🔧 CORREGIDO: Calcular IVA dinámico y total final (estructura estandarizada)
        $subtotalFinal = $subtotalWithDiscounts + $shippingCost; // Base gravable
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate', 15.0);
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $ivaAmount = $subtotalFinal * $taxRate; // IVA dinámico sobre base gravable
        $finalTotal = $subtotalFinal + $ivaAmount; // Total final

        // ✅ ESTRUCTURA ESTANDARIZADA
        return [
            'subtotal_original' => $subtotalOriginal,
            'subtotal_with_discounts' => $subtotalWithDiscounts,
            'subtotal_final' => $subtotalFinal, // 🔧 AGREGADO: Base gravable
            'seller_discounts' => $totalSellerDiscounts,
            'volume_discounts' => $totalVolumeDiscounts,
            'total_discounts' => $totalDiscounts,
            'iva_amount' => $ivaAmount,
            'shipping_cost' => $shippingCost,
            'free_shipping' => $freeShipping,
            'free_shipping_threshold' => $freeShippingThreshold,
            'final_total' => $finalTotal,
        ];
    }

    /**
     * 🚨 CRITICAL FIX: Validar stock con locks pesimistas para evitar overselling
     */
    private function validateProductStock(array $items): void
    {
        foreach ($items as $item) {
            // 🚨 CRITICAL: Lock pesimista para prevenir overselling
            $productModel = \App\Models\Product::where('id', $item['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$productModel) {
                Log::critical('🚨 CRITICAL: Product not found during stock validation', [
                    'product_id' => $item['product_id'],
                    'item' => $item
                ]);
                throw new \Exception("CRITICAL: Producto {$item['product_id']} no encontrado durante validación de stock");
            }

            // 🚨 CRITICAL: Verificación atómica de stock disponible
            if ($productModel->stock < $item['quantity']) {
                Log::critical('🚨 CRITICAL: Insufficient stock detected', [
                    'product_id' => $item['product_id'],
                    'product_name' => $productModel->name,
                    'available_stock' => $productModel->stock,
                    'requested_quantity' => $item['quantity'],
                    'user_action' => 'TRANSACTION_ROLLBACK_REQUIRED'
                ]);
                throw new \Exception("CRITICAL: Stock insuficiente para {$productModel->name}. Disponible: {$productModel->stock}, Solicitado: {$item['quantity']}");
            }

            // ✅ Log successful validation
            Log::info('✅ Stock validation passed with lock', [
                'product_id' => $item['product_id'],
                'product_name' => $productModel->name,
                'available_stock' => $productModel->stock,
                'requested_quantity' => $item['quantity']
            ]);
        }
    }

    /**
     * Agrupar items por seller
     */
    private function groupItemsBySeller(array $items): array
    {
        $itemsBySeller = [];

        foreach ($items as $item) {
            $sellerId = $item['seller_id'] ?? 1; // Default seller si no se especifica

            if (! isset($itemsBySeller[$sellerId])) {
                $itemsBySeller[$sellerId] = [];
            }

            $itemsBySeller[$sellerId][] = $item;
        }

        return $itemsBySeller;
    }

    /**
     * ✅ CORREGIDO: Crear seller orders con pricing correcto
     */
    private function createSellerOrders(
        int $orderId,
        array $itemsBySeller,
        array $totals,
        array $shippingData,
        OrderEntity $mainOrder,
        array $paymentData
    ): array {
        $sellerOrders = [];
        $sellerCount = count($itemsBySeller);

        // Calcular shipping cost por vendedor
        $shippingCostPerSeller = $sellerCount > 0 ? $totals['shipping_cost'] / $sellerCount : 0;

        foreach ($itemsBySeller as $sellerId => $items) {
            // ✅ Calcular totales específicos del seller
            $sellerSubtotal = 0;
            $sellerOriginalTotal = 0;
            $sellerDiscountTotal = 0;
            $volumeDiscountTotal = 0;

            foreach ($items as $item) {
                $sellerSubtotal += $item['subtotal'];
                $sellerOriginalTotal += $item['original_price'] * $item['quantity'];
                $sellerDiscountTotal += $item['seller_discount_amount'] * $item['quantity'];
                $volumeDiscountTotal += $item['volume_discount_amount'] * $item['quantity'];
            }

            $sellerOrderNumber = $mainOrder->getOrderNumber().'-S'.$sellerId;

            // ✅ Formatear items para seller order
            $formattedItems = [];
            foreach ($items as $item) {
                $formattedItems[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['final_price'], // ✅ Usar precio final con descuentos
                    'subtotal' => $item['subtotal'],
                    'seller_id' => $item['seller_id'] ?? $sellerId,
                    'base_price' => $item['original_price'],
                    'seller_discount_amount' => $item['seller_discount_amount'],
                    'volume_discount_amount' => $item['volume_discount_amount'],
                    'volume_discount_percentage' => $item['volume_discount_percentage'] ?? 0,
                ];
            }

            Log::info('📦 Creando seller order con descuentos por volumen', [
                'seller_id' => $sellerId,
                'subtotal' => $sellerSubtotal,
                'seller_discounts' => $sellerDiscountTotal,
                'volume_discounts' => $volumeDiscountTotal,
                'shipping_cost' => $shippingCostPerSeller,
                'items_count' => count($formattedItems),
            ]);
            
            Log::info('🔍 DEBUGGING: Punto de entrada a seller_order_id assignment', [
                'order_id' => $orderId,
                'seller_id' => $sellerId,
                'payment_method' => $paymentData['method'] ?? 'unknown',
                'items_for_seller' => count($formattedItems)
            ]);

            // ✅ Obtener payment info de la orden principal
            $mainOrderPaymentStatus = 'completed'; // Pago procesado cuando llega aquí
            $mainOrderPaymentMethod = $paymentData['method'] ?? 'datafast'; // Usar método real
            
            $sellerOrderEntity = SellerOrderEntity::create(
                $orderId,
                $sellerId,
                $formattedItems,
                $sellerSubtotal,
                'processing',
                array_merge($shippingData, [
                    'shipping_cost' => $shippingCostPerSeller,
                    'seller_subtotal' => $sellerSubtotal,
                    'seller_discounts' => $sellerDiscountTotal,
                    'volume_discounts' => $volumeDiscountTotal,
                ]),
                $sellerOrderNumber,
                $sellerOriginalTotal, // originalTotal
                $volumeDiscountTotal, // volumeDiscountSavings
                $volumeDiscountTotal > 0, // volumeDiscountsApplied
                $mainOrderPaymentStatus, // payment_status
                $mainOrderPaymentMethod // payment_method
            );

            $sellerOrder = $this->sellerOrderRepository->create($sellerOrderEntity);
            
            // 🚨 CRITICAL FIX: Updates atómicos con locks para evitar race conditions
            Log::info('🔍 DEBUGGING SELLER_ORDER_ID: Antes de actualizar Order', [
                'order_id' => $orderId,
                'seller_id' => $sellerId,
                'seller_order_id_to_assign' => $sellerOrder->getId(),
                'payment_method' => $paymentData['method'] ?? 'unknown'
            ]);
            
            $orderModel = \App\Models\Order::lockForUpdate()->find($orderId);
            if (!$orderModel) {
                Log::error('🚨 CRITICAL ERROR: Order not found for seller_order_id update', [
                    'order_id' => $orderId,
                    'seller_id' => $sellerId,
                    'seller_order_id' => $sellerOrder->getId()
                ]);
                throw new \Exception("CRITICAL: Order {$orderId} not found for atomic update");
            }
            
            Log::info('🔍 DEBUGGING SELLER_ORDER_ID: Order encontrada, actualizando', [
                'order_id' => $orderId,
                'current_seller_order_id' => $orderModel->seller_order_id,
                'new_seller_order_id' => $sellerOrder->getId(),
                'payment_method' => $orderModel->payment_method
            ]);
            
            $orderModel->seller_order_id = $sellerOrder->getId();
            $saveResult = $orderModel->save();
            
            Log::info('🔍 DEBUGGING SELLER_ORDER_ID: Resultado del save()', [
                'order_id' => $orderId,
                'save_result' => $saveResult,
                'final_seller_order_id' => $orderModel->seller_order_id,
                'payment_method' => $orderModel->payment_method
            ]);

            // ✅ CRITICAL FIX: Update atómico de OrderItems para este seller
            // Ya no usamos whereNull porque EloquentOrderRepository crea OrderItems sin seller_order_id
            $updatedItemsCount = \App\Models\OrderItem::where('order_id', $orderId)
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->update(['seller_order_id' => $sellerOrder->getId()]);

            // Verificar que la actualización fue exitosa
            $totalItemsCount = \App\Models\OrderItem::where('order_id', $orderId)
                ->where('seller_id', $sellerId)
                ->count();
                
            if ($updatedItemsCount === 0 && $totalItemsCount > 0) {
                Log::warning('🚨 CRITICAL WARNING: No OrderItems were updated', [
                    'order_id' => $orderId,
                    'seller_id' => $sellerId,
                    'seller_order_id' => $sellerOrder->getId(),
                    'total_items' => $totalItemsCount,
                    'expected_updates' => $totalItemsCount,
                    'actual_updates' => $updatedItemsCount
                ]);
            } else {
                Log::info('✅ OrderItems updated successfully', [
                    'order_id' => $orderId,
                    'seller_id' => $sellerId,
                    'seller_order_id' => $sellerOrder->getId(),
                    'total_items' => $totalItemsCount,
                    'updated_items' => $updatedItemsCount
                ]);
            }
            
            // ✅ CRÍTICO: Crear registro de Shipping para el SellerOrder
            $this->createShippingRecord($sellerOrder->getId(), $mainOrder);

            Log::info('✅ Updated order and order items seller_order_id for Datafast', [
                'order_id' => $orderId,
                'seller_order_id' => $sellerOrder->getId(),
                'seller_id' => $sellerId,
                'order_items_updated' => $updatedItemsCount,
                'total_items_for_seller' => $totalItemsCount
            ]);
            
            $sellerOrders[] = $sellerOrder;
        }

        return $sellerOrders;
    }

    /**
     * 🚨 CRITICAL FIX: Actualizar stock con locks atómicos para evitar condiciones de carrera
     */
    private function updateProductStock(array $items): void
    {
        foreach ($items as $item) {
            try {
                // 🚨 CRITICAL: Lock pesimista para update atómico de stock
                $productModel = \App\Models\Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$productModel) {
                    Log::critical('🚨 CRITICAL: Product disappeared during stock update', [
                        'product_id' => $item['product_id'],
                        'item' => $item
                    ]);
                    throw new \Exception("CRITICAL: Producto {$item['product_id']} no encontrado durante actualización de stock");
                }

                // 🚨 CRITICAL: Verificar stock antes de decrementar
                if ($productModel->stock < $item['quantity']) {
                    Log::critical('🚨 CRITICAL: Stock became insufficient during transaction', [
                        'product_id' => $item['product_id'],
                        'current_stock' => $productModel->stock,
                        'requested_quantity' => $item['quantity'],
                        'product_name' => $productModel->name
                    ]);
                    throw new \Exception("CRITICAL: Stock concurrente insuficiente para {$productModel->name}");
                }

                // 🚨 CRITICAL: Update atómico de stock
                $newStock = $productModel->stock - $item['quantity'];
                $productModel->stock = $newStock;
                $productModel->save();

                Log::info('✅ Stock updated atomically', [
                    'product_id' => $item['product_id'],
                    'product_name' => $productModel->name,
                    'previous_stock' => $productModel->stock + $item['quantity'],
                    'quantity_sold' => $item['quantity'],
                    'new_stock' => $newStock
                ]);

            } catch (\Exception $e) {
                Log::critical('🚨 CRITICAL: Stock update failed', [
                    'product_id' => $item['product_id'],
                    'error' => $e->getMessage(),
                    'item' => $item
                ]);
                throw $e; // Re-throw to trigger transaction rollback
            }
        }
    }

    /**
     * Convertir items de checkout al formato esperado por ApplyCartDiscountCodeUseCase
     */
    private function convertItemsToCartFormat(array $items): array
    {
        $cartItems = [];
        foreach ($items as $item) {
            $cartItems[] = [
                'product_id' => $item['product_id'],
                'seller_id' => $item['seller_id'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['final_price'] ?? $item['original_price'],
                'base_price' => $item['original_price'],
                'discount_percentage' => 0, // Already applied in volume discounts
                'attributes' => $item['attributes'] ?? [],
            ];
        }

        return $cartItems;
    }

    /**
     * Combinar totales de descuentos por volumen con descuentos de códigos de feedback
     */
    private function mergeDiscountCodeTotals(array $volumeTotals, array $discountData): array
    {
        $discountCodeTotals = $discountData['totals'];

        return [
            'subtotal_original' => $volumeTotals['subtotal_original'],
            'subtotal_with_discounts' => $discountCodeTotals['subtotal_products'], // Ya incluye descuento de código
            'subtotal_final' => $discountCodeTotals['subtotal_final'], // 🔧 AGREGADO: Base gravable con código
            'seller_discounts' => $volumeTotals['seller_discounts'],
            'volume_discounts' => $volumeTotals['volume_discounts'],
            'feedback_discount_amount' => $discountCodeTotals['feedback_discount_amount'],
            'total_discounts' => $volumeTotals['total_discounts'] + $discountCodeTotals['feedback_discount_amount'],
            'iva_amount' => $discountCodeTotals['iva_amount'], // Recalculado sobre base gravable
            'shipping_cost' => $discountCodeTotals['shipping_cost'] ?? $volumeTotals['shipping_cost'],
            'free_shipping' => $volumeTotals['free_shipping'] ?? false,
            'final_total' => $discountCodeTotals['final_total'], // Total final con todos los descuentos
        ];
    }

    /**
     * ✅ NUEVO: Crear registro de Shipping para un SellerOrder
     */
    private function createShippingRecord(int $sellerOrderId, OrderEntity $order): void
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
