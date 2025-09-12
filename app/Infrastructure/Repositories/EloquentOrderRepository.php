<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\OrderEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int|string $id): ?OrderEntity
    {
        $order = Order::with('items')->find($id);

        if (! $order) {
            return null;
        }

        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
            ];
        }

        // ✅ CORREGIDO: Deserializar shipping_data de JSON a array sin logs excesivos
        $shippingData = null;
        if ($order->shipping_data) {
            if (is_string($order->shipping_data)) {
                $shippingData = json_decode($order->shipping_data, true);
            } elseif (is_array($order->shipping_data)) {
                $shippingData = $order->shipping_data;
            }
        }

        // ✅ CRÍTICO: Usar reconstitute() para leer desde BD con parámetros en orden correcto
        return OrderEntity::reconstitute(
            $order->id,                          // int $id
            $order->user_id,                     // int $userId
            $order->seller_id,                   // ?int $sellerId
            $order->total,                       // float $total
            $order->status,                      // string $status
            $order->payment_id,                  // ?string $paymentId
            $order->payment_method,              // ?string $paymentMethod
            $order->payment_status,              // ?string $paymentStatus
            $shippingData,                       // ?array $shippingData
            $order->order_number,                // string $orderNumber
            $order->created_at,                  // string $createdAt
            $order->updated_at,                  // string $updatedAt
            $items,                              // array $items
            // Campos de descuentos por volumen (si existen)
            $order->original_total ?? null,      // ?float $originalTotal
            $order->volume_discount_savings ?? 0.0,  // float $volumeDiscountSavings
            $order->volume_discounts_applied ?? false,  // bool $volumeDiscountsApplied
            // 🔧 AGREGADO: Descuentos del vendedor
            $order->seller_discount_savings ?? 0.0,  // float $sellerDiscountSavings
            // Campos de pricing detallado (si existen)
            $order->subtotal_products ?? 0.0,        // float $subtotalProducts
            $order->iva_amount ?? 0.0,                // float $ivaAmount
            $order->shipping_cost ?? 0.0,             // float $shippingCost
            $order->total_discounts ?? 0.0,           // float $totalDiscounts
            $order->free_shipping ?? false,           // bool $freeShipping
            $order->free_shipping_threshold ?? null,  // ?float $freeShippingThreshold
            $order->pricing_breakdown ? json_decode($order->pricing_breakdown, true) : null,  // ?array $pricingBreakdown
            // ✅ NUEVOS: Campos de códigos de descuento de feedback
            $order->feedback_discount_code ?? null,        // ?string $feedbackDiscountCode
            $order->feedback_discount_amount ?? 0.0,       // float $feedbackDiscountAmount
            $order->feedback_discount_percentage ?? 0.0,   // float $feedbackDiscountPercentage
            // 🔧 AGREGADO: payment_details
            $order->payment_details ? json_decode($order->payment_details, true) : null  // ?array $paymentDetails
        );
    }

    public function save(OrderEntity $orderEntity): OrderEntity
    {
        return DB::transaction(function () use ($orderEntity) {
            try {
                // Crear o encontrar la orden
                $order = $orderEntity->getId() ? Order::find($orderEntity->getId()) : new Order;

                if (! $order) {
                    $order = new Order;
                }

                // ✅ CAMPOS BÁSICOS
                $order->user_id = $orderEntity->getUserId();
                $order->seller_id = $orderEntity->getSellerId();
                $order->total = $orderEntity->getTotal();
                $order->status = $orderEntity->getStatus();
                $order->payment_id = $orderEntity->getPaymentId();
                $order->payment_method = $orderEntity->getPaymentMethod();
                $order->payment_status = $orderEntity->getPaymentStatus();

                // ✅ CORREGIDO: shipping_data - dejar que Laravel cast 'array' maneje el JSON automáticamente
                $shippingData = $orderEntity->getShippingData();
                
                // 🔍 LOGGING TEMPORAL: Tracking de shipping_data persistence
                Log::info("🔍 EloquentOrderRepository.save() - SHIPPING_DATA TRACKING", [
                    'order_id' => $orderEntity->getId(),
                    'shipping_data_from_entity' => $shippingData,
                    'shipping_data_type' => gettype($shippingData),
                    'is_array' => is_array($shippingData),
                    'has_identification' => is_array($shippingData) && isset($shippingData['identification']),
                    'identification_value' => is_array($shippingData) ? ($shippingData['identification'] ?? 'NO_SET') : 'NOT_ARRAY',
                    'shipping_data_count' => is_array($shippingData) ? count($shippingData) : 'NOT_COUNTABLE'
                ]);
                
                // ✅ CORRECCIÓN CRÍTICA: Pasar array directamente, Laravel cast 'array' hará json_encode() automáticamente
                if (is_array($shippingData)) {
                    $order->shipping_data = $shippingData;  // ✅ ARRAY DIRECTO
                } elseif (is_string($shippingData)) {
                    $order->shipping_data = json_decode($shippingData, true) ?: [];  // ✅ DECODIFICAR PRIMERO
                } else {
                    $order->shipping_data = [];  // ✅ ARRAY VACÍO
                }
                
                // 🔍 LOGGING TEMPORAL: Verificar qué se pasará a Laravel
                Log::info("🔍 EloquentOrderRepository.save() - ARRAY PARA LARAVEL CAST", [
                    'order_id' => $orderEntity->getId(),
                    'shipping_data_array' => $order->shipping_data,
                    'is_array' => is_array($order->shipping_data),
                    'array_count' => is_array($order->shipping_data) ? count($order->shipping_data) : 'NOT_ARRAY'
                ]);

                // ✅ CAMPOS DE DESCUENTOS POR VOLUMEN CON VALORES POR DEFECTO Y VALIDACIÓN DE MÉTODOS
                if (method_exists($orderEntity, 'getOriginalTotal') && $orderEntity->getOriginalTotal() !== null) {
                    $order->original_total = (float) $orderEntity->getOriginalTotal();
                } else {
                    $order->original_total = (float) $orderEntity->getTotal();
                }

                if (method_exists($orderEntity, 'getVolumeDiscountSavings') && $orderEntity->getVolumeDiscountSavings() !== null) {
                    $order->volume_discount_savings = (float) $orderEntity->getVolumeDiscountSavings();
                } else {
                    $order->volume_discount_savings = 0.0;
                }

                if (method_exists($orderEntity, 'getVolumeDiscountsApplied') && $orderEntity->getVolumeDiscountsApplied() !== null) {
                    $order->volume_discounts_applied = (bool) $orderEntity->getVolumeDiscountsApplied();
                } else {
                    $order->volume_discounts_applied = false;
                }

                // 🔧 AGREGADO: Descuentos del vendedor
                if (method_exists($orderEntity, 'getSellerDiscountSavings') && $orderEntity->getSellerDiscountSavings() !== null) {
                    $order->seller_discount_savings = (float) $orderEntity->getSellerDiscountSavings();
                } else {
                    $order->seller_discount_savings = 0.0;
                }

                // ✅ CAMPOS DE PRICING DETALLADO CON VALIDACIÓN SEGURA
                if (method_exists($orderEntity, 'getSubtotalProducts') && $orderEntity->getSubtotalProducts() !== null) {
                    $order->subtotal_products = (float) $orderEntity->getSubtotalProducts();
                } else {
                    $order->subtotal_products = 0.0;
                }

                if (method_exists($orderEntity, 'getIvaAmount') && $orderEntity->getIvaAmount() !== null) {
                    $order->iva_amount = (float) $orderEntity->getIvaAmount();
                } else {
                    $order->iva_amount = 0.0;
                }

                if (method_exists($orderEntity, 'getShippingCost') && $orderEntity->getShippingCost() !== null) {
                    $order->shipping_cost = (float) $orderEntity->getShippingCost();
                } else {
                    $order->shipping_cost = 0.0;
                }

                if (method_exists($orderEntity, 'getTotalDiscounts') && $orderEntity->getTotalDiscounts() !== null) {
                    $order->total_discounts = (float) $orderEntity->getTotalDiscounts();
                } else {
                    $order->total_discounts = 0.0;
                }

                if (method_exists($orderEntity, 'getFreeShipping') && $orderEntity->getFreeShipping() !== null) {
                    $order->free_shipping = (bool) $orderEntity->getFreeShipping();
                } else {
                    $order->free_shipping = false;
                }

                // ✅ CRÍTICO: free_shipping_threshold - NUNCA puede ser array
                if (method_exists($orderEntity, 'getFreeShippingThreshold')) {
                    $threshold = $orderEntity->getFreeShippingThreshold();
                    if (is_numeric($threshold)) {
                        $order->free_shipping_threshold = (float) $threshold;
                    } elseif (is_array($threshold) && isset($threshold['amount'])) {
                        $order->free_shipping_threshold = (float) $threshold['amount'];
                    } else {
                        $order->free_shipping_threshold = null;
                    }
                } else {
                    $order->free_shipping_threshold = null;
                }

                // ✅ CRÍTICO: pricing_breakdown - convertir array a JSON string
                if (method_exists($orderEntity, 'getPricingBreakdown')) {
                    $breakdown = $orderEntity->getPricingBreakdown();
                    if (is_array($breakdown)) {
                        $order->pricing_breakdown = json_encode($breakdown);
                    } elseif (is_string($breakdown)) {
                        $order->pricing_breakdown = $breakdown;
                    } else {
                        $order->pricing_breakdown = null;
                    }
                } else {
                    $order->pricing_breakdown = null;
                }

                // ✅ NUEVOS: Campos de códigos de descuento de feedback
                if (method_exists($orderEntity, 'getFeedbackDiscountCode') && $orderEntity->getFeedbackDiscountCode() !== null) {
                    $order->feedback_discount_code = (string) $orderEntity->getFeedbackDiscountCode();
                } else {
                    $order->feedback_discount_code = null;
                }

                if (method_exists($orderEntity, 'getFeedbackDiscountAmount') && $orderEntity->getFeedbackDiscountAmount() !== null) {
                    $order->feedback_discount_amount = (float) $orderEntity->getFeedbackDiscountAmount();
                } else {
                    $order->feedback_discount_amount = 0.0;
                }

                if (method_exists($orderEntity, 'getFeedbackDiscountPercentage') && $orderEntity->getFeedbackDiscountPercentage() !== null) {
                    $order->feedback_discount_percentage = (float) $orderEntity->getFeedbackDiscountPercentage();
                } else {
                    $order->feedback_discount_percentage = 0.0;
                }

                // 🔧 AGREGADO: payment_details - convertir array a JSON string
                if (method_exists($orderEntity, 'getPaymentDetails')) {
                    $paymentDetails = $orderEntity->getPaymentDetails();
                    if (is_array($paymentDetails)) {
                        $order->payment_details = json_encode($paymentDetails);
                    } elseif (is_string($paymentDetails)) {
                        $order->payment_details = $paymentDetails;
                    } else {
                        $order->payment_details = null;
                    }
                } else {
                    $order->payment_details = null;
                }

                // ✅ GENERAR NÚMERO DE ORDEN
                $orderNumber = $orderEntity->getOrderNumber();
                if (empty($orderNumber) || strpos($orderNumber, '-TMP') !== false) {
                    $order->order_number = Order::generateOrderNumber();
                } else {
                    $order->order_number = $orderNumber;
                }

                // ✅ GUARDAR LA ORDEN
                $order->save();

                // ✅ CREAR ITEMS CON INFORMACIÓN DE DESCUENTOS POR VOLUMEN
                $itemEntities = $orderEntity->getItems();
                $items = [];

                // ✅ CORREGIDO: Crear OrderItems si hay items, independientemente del seller_id de la orden
                // Los OrderItems necesitan existir para después ser asociados con SellerOrders
                if (! empty($itemEntities)) {
                    // Si es una actualización, eliminar items existentes
                    if ($orderEntity->getId()) {
                        OrderItem::where('order_id', $order->id)->delete();
                    }

                    // Crear los items de la orden con información completa del producto
                    foreach ($itemEntities as $item) {
                        // ✅ NUEVO: Obtener información del producto para guardarla
                        $product = \App\Models\Product::find($item['product_id']);

                        $orderItem = new OrderItem;
                        $orderItem->order_id = $order->id;
                        $orderItem->product_id = $item['product_id'];
                        $orderItem->quantity = $item['quantity'];
                        $orderItem->price = $item['price'];
                        $orderItem->subtotal = $item['subtotal'] ?? ($item['price'] * $item['quantity']);

                        // ✅ NUEVO: Guardar información del producto para que no se pierda
                        $orderItem->product_name = $product ? $product->name : 'Producto no disponible';
                        $orderItem->product_sku = $product ? $product->sku : null;
                        $orderItem->product_image = $product ? $product->getMainImageUrl() : null;
                        $orderItem->seller_id = $product ? $product->seller_id : ($item['seller_id'] ?? null);

                        // ✅ CAMPOS DE DESCUENTOS POR VOLUMEN CON VALORES POR DEFECTO
                        $orderItem->original_price = $item['original_price'] ?? $item['price'];
                        $orderItem->volume_discount_percentage = $item['volume_discount_percentage'] ?? 0;
                        $orderItem->volume_savings = $item['volume_savings'] ?? 0;
                        $orderItem->discount_label = $item['discount_label'] ?? null;

                        $orderItem->save();

                        $items[] = [
                            'id' => $orderItem->id,
                            'product_id' => $orderItem->product_id,
                            'product_name' => $orderItem->product_name,
                            'product_sku' => $orderItem->product_sku,
                            'product_image' => $orderItem->product_image,
                            'quantity' => $orderItem->quantity,
                            'price' => $orderItem->price,
                            'subtotal' => $orderItem->subtotal,
                            'original_price' => $orderItem->original_price,
                            'volume_discount_percentage' => $orderItem->volume_discount_percentage,
                            'volume_savings' => $orderItem->volume_savings,
                            'discount_label' => $orderItem->discount_label,
                            'seller_id' => $orderItem->seller_id,
                        ];
                    }
                }

                // ✅ REFRESCAR MODELO
                $order->refresh();

                // ✅ DEVOLVER ENTIDAD ACTUALIZADA USANDO findById() QUE YA ESTÁ CORREGIDO
                return $this->findById($order->id);

            } catch (\Exception $e) {
                Log::error('❌ Error en EloquentOrderRepository::save()', [
                    'error' => $e->getMessage(),
                    'user_id' => $orderEntity->getUserId(),
                    'seller_id' => $orderEntity->getSellerId(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * ✅ CORRECCIÓN CRÍTICA: Método para DATAFAST que no usa transacciones anidadas
     * Para evitar conflictos de aislamiento SERIALIZABLE
     */
    public function saveWithoutTransaction(OrderEntity $orderEntity): OrderEntity
    {
        try {
            // Crear o encontrar la orden
            $order = $orderEntity->getId() ? Order::find($orderEntity->getId()) : new Order;

            if (!$order) {
                $order = new Order;
            }

            // ✅ CAMPOS BÁSICOS
            $order->user_id = $orderEntity->getUserId();
            $order->seller_id = $orderEntity->getSellerId();
            $order->total = $orderEntity->getTotal();
            $order->status = $orderEntity->getStatus();
            $order->payment_id = $orderEntity->getPaymentId();
            $order->payment_method = $orderEntity->getPaymentMethod();
            $order->payment_status = $orderEntity->getPaymentStatus();

            // ✅ SERIALIZAR shipping_data como JSON string
            if ($orderEntity->getShippingData()) {
                $order->shipping_data = json_encode($orderEntity->getShippingData());
            } else {
                $order->shipping_data = null;
            }

            // ✅ CAMPOS DE PRICING DETALLADO
            $order->original_total = $orderEntity->getOriginalTotal() ?? 0.0;
            $order->volume_discount_savings = $orderEntity->getVolumeDiscountSavings() ?? 0.0;
            $order->volume_discounts_applied = $orderEntity->getVolumeDiscountsApplied() ?? false;
            // 🔧 CRÍTICO: Agregar seller_discount_savings que faltaba!
            $order->seller_discount_savings = $orderEntity->getSellerDiscountSavings() ?? 0.0;
            $order->subtotal_products = $orderEntity->getSubtotalProducts() ?? 0.0;
            $order->iva_amount = $orderEntity->getIvaAmount() ?? 0.0;
            $order->shipping_cost = $orderEntity->getShippingCost() ?? 0.0;
            $order->total_discounts = $orderEntity->getTotalDiscounts() ?? 0.0;
            $order->free_shipping = $orderEntity->getFreeShipping() ?? false;

            // Guardar orden SIN TRANSACCIÓN
            $order->save();

            // Crear OrderItems
            if ($orderEntity->getItems()) {
                // Eliminar items existentes si es una actualización
                if ($order->id) {
                    OrderItem::where('order_id', $order->id)->delete();
                }

                foreach ($orderEntity->getItems() as $itemData) {
                    $orderItem = new OrderItem;
                    $orderItem->order_id = $order->id;
                    $orderItem->product_id = $itemData['product_id'];
                    $orderItem->quantity = $itemData['quantity'];
                    $orderItem->price = $itemData['price'];
                    $orderItem->subtotal = $itemData['subtotal'] ?? ($itemData['price'] * $itemData['quantity']);
                    
                    // ✅ CRÍTICO: Obtener seller_id desde el producto
                    if ($itemData['product_id']) {
                        try {
                            $product = \App\Models\Product::find($itemData['product_id']);
                            if ($product) {
                                $orderItem->seller_id = $product->seller_id;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Error obteniendo seller_id del producto', [
                                'product_id' => $itemData['product_id'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    $orderItem->save();
                }
            }

            // Refrescar y retornar la entidad
            $order->refresh();
            
            return new OrderEntity(
                $order->user_id,
                $order->seller_id,
                [],  // Items se cargan por separado para simplificar
                $order->total,
                $order->status,
                $order->payment_id,
                $order->payment_method,
                $order->payment_status,
                $order->shipping_data ? json_decode($order->shipping_data, true) : null,
                $order->order_number,
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );

        } catch (\Exception $e) {
            Log::error('❌ Error en EloquentOrderRepository::saveWithoutTransaction()', [
                'error' => $e->getMessage(),
                'user_id' => $orderEntity->getUserId(),
                'seller_id' => $orderEntity->getSellerId(),
            ]);
            throw $e;
        }
    }

    public function updatePaymentInfo(int $orderId, array $paymentInfo): bool
    {
        Log::info("🔄 EloquentOrderRepository.updatePaymentInfo INICIADO", [
            'orderId' => $orderId,
            'paymentInfo' => $paymentInfo
        ]);

        $order = Order::find($orderId);

        if (! $order) {
            Log::error("❌ ORDER NO ENCONTRADA", ['orderId' => $orderId]);
            return false;
        }

        Log::info("✅ ORDER ENCONTRADA", [
            'orderId' => $orderId,
            'current_payment_status' => $order->payment_status,
            'current_payment_id' => $order->payment_id,
            'current_payment_method' => $order->payment_method
        ]);

        $oldPaymentStatus = $order->payment_status;
        $oldPaymentId = $order->payment_id;
        $oldPaymentMethod = $order->payment_method;

        $order->payment_id = $paymentInfo['payment_id'] ?? $order->payment_id;
        $order->payment_status = $paymentInfo['payment_status'] ?? $order->payment_status;
        $order->payment_method = $paymentInfo['payment_method'] ?? $order->payment_method;

        // Si hay detalles de pago adicionales, los actualizamos
        if (isset($paymentInfo['payment_details'])) {
            $existingDetails = $order->payment_details ?: [];
            $order->payment_details = array_merge($existingDetails, $paymentInfo['payment_details']);
        }

        if (isset($paymentInfo['status'])) {
            $order->status = $paymentInfo['status'];
        }

        Log::info("🔄 CAMPOS ACTUALIZADOS ANTES DEL SAVE", [
            'orderId' => $orderId,
            'changes' => [
                'payment_id' => ['old' => $oldPaymentId, 'new' => $order->payment_id],
                'payment_status' => ['old' => $oldPaymentStatus, 'new' => $order->payment_status],
                'payment_method' => ['old' => $oldPaymentMethod, 'new' => $order->payment_method]
            ],
            'isDirty' => $order->isDirty(),
            'dirtyFields' => $order->getDirty()
        ]);

        try {
            $saveResult = $order->save();
            
            if ($saveResult) {
                // Verificar que realmente se guardó releyendo desde BD
                $savedOrder = Order::find($orderId);
                Log::info("✅ ORDER.SAVE() EXITOSO - VERIFICANDO PERSISTENCIA", [
                    'orderId' => $orderId,
                    'saveResult' => $saveResult,
                    'verification' => [
                        'payment_id' => $savedOrder->payment_id,
                        'payment_status' => $savedOrder->payment_status,
                        'payment_method' => $savedOrder->payment_method
                    ],
                    'wasActuallyPersisted' => $savedOrder->payment_status === $paymentInfo['payment_status']
                ]);
            } else {
                Log::error("❌ ORDER.SAVE() FALLÓ", [
                    'orderId' => $orderId,
                    'saveResult' => $saveResult
                ]);
            }
            
            return $saveResult;
        } catch (\Exception $e) {
            Log::error("❌ EXCEPCIÓN EN ORDER.SAVE()", [
                'orderId' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function findOrdersByUserId(int $userId): array
    {
        $orders = Order::where('user_id', $userId)->orderBy('created_at', 'desc')->get();

        $result = [];
        foreach ($orders as $order) {
            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                [], // No items for performance
                $order->total,
                $order->status,
                $order->payment_id,
                $order->payment_method,
                $order->payment_status,
                $shippingData,
                $order->order_number,
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    public function getOrderDetails(int $orderId): array
    {
        $order = Order::with(['user'])->find($orderId);

        if (! $order) {
            return [];
        }

        $items = [];

        // ✅ OBTENER ITEMS DE SELLER_ORDERS en lugar de order_items directos
        $sellerOrders = SellerOrder::where('order_id', $orderId)->get();

        if ($sellerOrders->count() > 0) {
            // Si hay seller orders, obtener items de ahí
            foreach ($sellerOrders as $sellerOrder) {
                $sellerOrderItems = OrderItem::where('seller_order_id', $sellerOrder->id)
                    ->with('product')
                    ->get();

                foreach ($sellerOrderItems as $item) {
                    // Obtener el precio original del producto
                    $originalPrice = $item->product->price ?? $item->price;
                    $sellerDiscount = $item->product->discount_percentage ?? 0;
                    
                    // Calcular si hubo descuento por volumen
                    $volumeDiscountPercentage = 0;
                    $volumeSavings = 0;
                    
                    // Si el precio del item es menor que el precio original con descuento del seller
                    // entonces hay descuento por volumen aplicado
                    $priceAfterSellerDiscount = $originalPrice * (1 - $sellerDiscount / 100);
                    if ($item->quantity >= 3 && $priceAfterSellerDiscount > ($item->price / $item->quantity)) {
                        // Calcular el porcentaje de descuento por volumen
                        $pricePerUnit = $item->price / $item->quantity;
                        $volumeDiscountPercentage = round(((1 - ($pricePerUnit / $priceAfterSellerDiscount)) * 100), 2);
                        $volumeSavings = ($priceAfterSellerDiscount - $pricePerUnit) * $item->quantity;
                    }
                    
                    $items[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? 'Producto',
                        'product_sku' => $item->product->sku ?? '',
                        'product_image' => $item->product->images[0]['thumbnail'] ?? null,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        // Nuevos campos para el desglose de descuentos
                        'original_price' => $originalPrice * $item->quantity,
                        'original_price_per_unit' => $originalPrice,
                        'seller_discount_percentage' => $sellerDiscount,
                        'volume_discount_percentage' => $volumeDiscountPercentage,
                        'volume_savings' => $volumeSavings,
                    ];
                }
            }
        } else {
            // Fallback: obtener items directos de la orden (para órdenes antiguas)
            $directItems = OrderItem::where('order_id', $orderId)
                ->whereNull('seller_order_id')
                ->with('product')
                ->get();

            foreach ($directItems as $item) {
                // Obtener el precio original del producto
                $originalPrice = $item->product->price ?? $item->price;
                $sellerDiscount = $item->product->discount_percentage ?? 0;
                
                // Calcular si hubo descuento por volumen
                $volumeDiscountPercentage = 0;
                $volumeSavings = 0;
                
                // Si el precio del item es menor que el precio original con descuento del seller
                // entonces hay descuento por volumen aplicado
                $priceAfterSellerDiscount = $originalPrice * (1 - $sellerDiscount / 100);
                if ($item->quantity >= 3 && $priceAfterSellerDiscount > ($item->price / $item->quantity)) {
                    // Calcular el porcentaje de descuento por volumen
                    $pricePerUnit = $item->price / $item->quantity;
                    $volumeDiscountPercentage = round(((1 - ($pricePerUnit / $priceAfterSellerDiscount)) * 100), 2);
                    $volumeSavings = ($priceAfterSellerDiscount - $pricePerUnit) * $item->quantity;
                }
                
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Producto',
                    'product_sku' => $item->product->sku ?? '',
                    'product_image' => $item->product->images[0]['thumbnail'] ?? null,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    // Nuevos campos para el desglose de descuentos
                    'original_price' => $originalPrice * $item->quantity,
                    'original_price_per_unit' => $originalPrice,
                    'seller_discount_percentage' => $sellerDiscount,
                    'volume_discount_percentage' => $volumeDiscountPercentage,
                    'volume_savings' => $volumeSavings,
                ];
            }
        }

        // ✅ FIXED: Deserializar shipping_data y usar nombre correcto para frontend
        $shippingData = null;
        if ($order->shipping_data) {
            if (is_string($order->shipping_data)) {
                $shippingData = json_decode($order->shipping_data, true);
            } elseif (is_array($order->shipping_data)) {
                $shippingData = $order->shipping_data;
            }
        }

        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'seller_id' => $order->seller_id,
            'user_name' => $order->user->name ?? 'Usuario',
            'user_email' => $order->user->email ?? '',
            'items' => $items,
            'total' => $order->total,
            'status' => $order->status,
            'payment_id' => $order->payment_id,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'shippingData' => $shippingData, // ✅ FIXED: Usar clave correcta para frontend
            'orderNumber' => $order->order_number, // ✅ FIXED: CamelCase para consistencia
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
            // ✅ NUEVO: Campos de descuentos por volumen
            'original_total' => $order->original_total ?? null,
            'volume_discount_savings' => $order->volume_discount_savings ?? 0.0,
            'seller_discount_savings' => $order->seller_discount_savings ?? 0.0,
            'volume_discounts_applied' => $order->volume_discounts_applied ?? false,
            // ✅ NUEVO: Campos de pricing detallado
            'subtotal_products' => $order->subtotal_products ?? 0.0,
            'iva_amount' => $order->iva_amount ?? 0.0,
            'shipping_cost' => $order->shipping_cost ?? 0.0,
            'total_discounts' => $order->total_discounts ?? 0.0,
            'free_shipping' => $order->free_shipping ?? false,
            'free_shipping_threshold' => $order->free_shipping_threshold ?? null,
            'pricing_breakdown' => $order->pricing_breakdown ? json_decode($order->pricing_breakdown, true) : null,
            // ✅ NUEVO: Campos de códigos de descuento de feedback
            'feedback_discount_code' => $order->feedback_discount_code ?? null,
            'feedback_discount_amount' => $order->feedback_discount_amount ?? 0.0,
            'feedback_discount_percentage' => $order->feedback_discount_percentage ?? 0.0,
        ];
    }

    /**
     * Check if an order contains a specific product
     */
    public function orderContainsProduct(int $orderId, int $productId): bool
    {
        return OrderItem::where('order_id', $orderId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Create a new order
     */
    public function create(OrderEntity $orderEntity): OrderEntity
    {
        // Reuse save method for creation
        return $this->save($orderEntity);
    }

    /**
     * Get orders for a specific user
     */
    public function getOrdersForUser(int $userId, int $limit = 10, int $offset = 0): array
    {
        $orders = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                [], // No items for performance
                $order->total,
                $order->status,
                $order->payment_id ?? null,
                $order->payment_method ?? null,
                $order->payment_status ?? null,
                $shippingData,
                $order->order_number ?? '',
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    /**
     * Get orders for a specific seller
     */
    public function getOrdersForSeller(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $orders = Order::where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            // Cargar items solo si es necesario
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                $items,
                $order->total,
                $order->status,
                $order->payment_id ?? null,
                $order->payment_method ?? null,
                $order->payment_status ?? null,
                $shippingData,
                $order->order_number ?? '',
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    /**
     * Get filtered orders for a seller
     */
    public function getFilteredOrdersForSeller(int $sellerId, array $filters, int $limit = 10, int $offset = 0): array
    {
        $query = Order::where('seller_id', $sellerId);

        // Aplicar filtros
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Ordenar por fecha de creación descendente (más recientes primero)
        $query->orderBy('created_at', 'desc');

        // Paginar resultados
        $orders = $query->skip($offset)->take($limit)->get();

        // Transformar a entidades
        $result = [];
        foreach ($orders as $order) {
            // Cargar items solo si se solicita
            $items = [];
            if (! empty($filters['include_items'])) {
                foreach ($order->items as $item) {
                    $items[] = [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                    ];
                }
            }

            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                $items,
                $order->total,
                $order->status,
                $order->payment_id ?? null,
                $order->payment_method ?? null,
                $order->payment_status ?? null,
                $shippingData,
                $order->order_number ?? '',
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    /**
     * Get order statistics for a seller
     */
    public function getSellerOrderStats(int $sellerId): array
    {
        $totalOrders = Order::where('seller_id', $sellerId)->count();

        $pendingOrders = $this->countOrdersByStatus($sellerId, 'pending');
        $processingOrders = $this->countOrdersByStatus($sellerId, 'processing');
        $shippedOrders = $this->countOrdersByStatus($sellerId, 'shipped');
        $deliveredOrders = $this->countOrdersByStatus($sellerId, 'delivered');
        $cancelledOrders = $this->countOrdersByStatus($sellerId, 'cancelled');

        $totalSales = $this->getTotalSalesForSeller($sellerId);

        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'processingOrders' => $processingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'totalSales' => $totalSales,
        ];
    }

    /**
     * Count orders by status for a seller
     */
    public function countOrdersByStatus(int $sellerId, string $status): int
    {
        return Order::where('seller_id', $sellerId)
            ->where('status', $status)
            ->count();
    }

    /**
     * Get total sales amount for a seller
     */
    public function getTotalSalesForSeller(int $sellerId, ?string $dateFrom = null, ?string $dateTo = null): float
    {
        $query = Order::where('seller_id', $sellerId)
            ->whereNotIn('status', ['cancelled'])
            ->whereIn('payment_status', ['completed', 'succeeded']);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->sum('total');
    }

    /**
     * Get recent orders for a seller
     */
    public function getRecentOrdersForSeller(int $sellerId, int $limit = 5): array
    {
        $orders = Order::where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->with(['user:id,name,email', 'items'])
            ->get();

        $result = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }

            $result[] = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'user_name' => $order->user->name ?? 'Usuario',
                'user_email' => $order->user->email ?? '',
                'total' => $order->total,
                'status' => $order->status,
                'items_count' => count($items),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $result;
    }

    /**
     * Get popular products from seller's orders
     */
    public function getPopularProductsForSeller(int $sellerId, int $limit = 5): array
    {
        $popularProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.seller_id', $sellerId)
            ->whereNotIn('orders.status', ['cancelled'])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.slug as product_slug',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                DB::raw('SUM(order_items.subtotal) as total_sales')
            )
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();

        return $popularProducts->toArray();
    }

    /**
     * Search orders for a seller
     */
    public function searchSellerOrders(int $sellerId, string $query, int $limit = 10, int $offset = 0): array
    {
        $orders = Order::where('seller_id', $sellerId)
            ->where(function ($q) use ($query) {
                $q->where('order_number', 'like', "%{$query}%")
                    ->orWhereHas('user', function ($userQuery) use ($query) {
                        $userQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    });
            })
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                [], // No items for performance
                $order->total,
                $order->status,
                $order->payment_id ?? null,
                $order->payment_method ?? null,
                $order->payment_status ?? null,
                $shippingData,
                $order->order_number ?? '',
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    /**
     * Count total orders for a seller
     */
    public function countTotalOrdersForSeller(int $sellerId): int
    {
        return Order::where('seller_id', $sellerId)->count();
    }

    /**
     * Get sales data by period for seller
     */
    public function getSellerSalesByPeriod(int $sellerId, string $period = 'day', int $limit = 30): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };

        $rawQuery = match ($period) {
            'day' => 'DATE(created_at)',
            'week' => "CONCAT(YEAR(created_at), '-', WEEK(created_at))",
            'month' => "CONCAT(YEAR(created_at), '-', MONTH(created_at))",
            'year' => 'YEAR(created_at)',
            default => 'DATE(created_at)'
        };

        $results = DB::table('orders')
            ->select(
                DB::raw("{$rawQuery} as period"),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('COUNT(*) as order_count')
            )
            ->where('seller_id', $sellerId)
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->limit($limit)
            ->get();

        // Reverse para que estén en orden cronológico
        return array_reverse($results->toArray());
    }

    /**
     * Get order count by status for a specific seller
     */
    public function getOrderCountByStatus(int $sellerId): array
    {
        $results = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('seller_id', $sellerId)
            ->groupBy('status')
            ->get();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->status] = $row->count;
        }

        // Asegurar que todos los estados tengan un valor, aunque sea 0
        $allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'paid', 'cancelled'];
        foreach ($allStatuses as $status) {
            if (! isset($counts[$status])) {
                $counts[$status] = 0;
            }
        }

        return $counts;
    }

    /**
     * Get customer list for a seller with their order counts and total spent
     */
    public function getSellerCustomers(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $customers = DB::table('orders')
            ->select(
                'orders.user_id',
                'users.name',
                'users.email',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.total) as total_spent'),
                DB::raw('MAX(orders.created_at) as last_order_date')
            )
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.seller_id', $sellerId)
            ->whereNotIn('orders.status', ['cancelled'])
            ->groupBy('orders.user_id', 'users.name', 'users.email')
            ->orderBy('total_spent', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $customers->toArray();
    }

    /**
     * Get orders with specific product
     */
    public function getOrdersWithProduct(int $sellerId, int $productId, int $limit = 10, int $offset = 0): array
    {
        $orders = Order::where('seller_id', $sellerId)
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->with(['items' => function ($query) use ($productId) {
                $query->where('product_id', $productId);
            }, 'user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }

            $result[] = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'user_name' => $order->user->name ?? 'Usuario',
                'user_email' => $order->user->email ?? '',
                'total' => $order->total,
                'status' => $order->status,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(int $orderId, string $reason = ''): bool
    {
        $order = Order::find($orderId);

        if (! $order) {
            return false;
        }

        // Verificar si el pedido puede ser cancelado
        if (! in_array($order->status, ['pending', 'processing', 'paid'])) {
            return false;
        }

        $order->status = 'cancelled';

        // Si se proporciona una razón, la guardamos en shipping_data
        if (! empty($reason)) {
            // ✅ CORREGIDO: Deserialización segura antes de modificar SIN logs
            $shippingData = [];
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true) ?: [];
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $shippingData['cancel_reason'] = $reason;
            $order->shipping_data = json_encode($shippingData);
        }

        return $order->save();
    }

    /**
     * Get orders awaiting shipment (processing status)
     */
    public function getOrdersAwaitingShipment(int $sellerId, int $limit = 10, int $offset = 0): array
    {
        $orders = Order::where('seller_id', $sellerId)
            ->where('status', 'processing')
            ->where('payment_status', 'completed')
            ->with(['user:id,name,email', 'items.product:id,name,sku'])
            ->orderBy('created_at', 'asc') // Los más antiguos primero
            ->skip($offset)
            ->take($limit)
            ->get();

        $result = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Producto',
                    'product_sku' => $item->product->sku ?? '',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }

            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = [];
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true) ?: [];
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'user_name' => $order->user->name ?? 'Usuario',
                'user_email' => $order->user->email ?? '',
                'total' => $order->total,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'shipping_data' => $shippingData,
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Update order shipping information
     */
    public function updateShippingInfo(int $orderId, array $shippingInfo): bool
    {
        $order = Order::find($orderId);

        if (! $order) {
            return false;
        }

        // ✅ CORREGIDO: Deserialización segura antes de modificar SIN logs
        $existingShippingData = [];
        if ($order->shipping_data) {
            if (is_string($order->shipping_data)) {
                $existingShippingData = json_decode($order->shipping_data, true) ?: [];
            } elseif (is_array($order->shipping_data)) {
                $existingShippingData = $order->shipping_data;
            }
        }

        $updatedShippingData = array_merge($existingShippingData, $shippingInfo);

        // Actualizar datos de envío
        $order->shipping_data = json_encode($updatedShippingData);

        // Si se incluye tracking_number, actualizamos el estado a 'shipped' si es apropiado
        if (isset($shippingInfo['tracking_number']) && $order->status === 'processing') {
            $order->status = 'shipped';
        }

        return $order->save();
    }

    /**
     * Get average order value for a seller
     */
    public function getAverageOrderValue(int $sellerId, ?string $dateFrom = null, ?string $dateTo = null): float
    {
        $query = Order::where('seller_id', $sellerId)
            ->whereNotIn('status', ['cancelled']);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totalOrders = $query->count();
        if ($totalOrders === 0) {
            return 0;
        }

        $totalSales = $query->sum('total');

        return $totalSales / $totalOrders;
    }

    public function findCompletedOrdersByUserId(int $userId): array
    {
        $orders = Order::where('user_id', $userId)
            ->whereIn('status', ['completed', 'delivered', 'shipped']) // ✅ AGREGAR 'shipped'
            ->orderBy('created_at', 'desc')
            ->with(['items.product:id,name,slug,images', 'sellerOrders.items'])
            ->get();

        $result = [];
        foreach ($orders as $order) {
            // Transformar a entidad OrderEntity
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'product' => [
                        'id' => $item->product->id ?? null,
                        'name' => $item->product->name ?? 'Producto no disponible',
                        'slug' => $item->product->slug ?? '',
                        'image' => isset($item->product->images[0]) ? $item->product->images[0]['thumbnail'] : null,
                    ],
                ];
            }

            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $orderEntity = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                $items,
                $order->total,
                $order->status,
                $order->payment_id,
                $order->payment_method,
                $order->payment_status,
                $shippingData,
                $order->order_number,
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );

            // ✅ CORREGIDO: Verificar si el método setSellerOrders existe antes de usarlo
            if (method_exists($orderEntity, 'setSellerOrders')) {
                $sellerOrdersArray = $order->sellerOrders ? $order->sellerOrders->toArray() : null;
                $orderEntity->setSellerOrders($sellerOrdersArray);
            }

            $result[] = $orderEntity;
        }

        return $result;
    }

    /**
     * Get orders with specific product for a user
     */
    public function getOrdersWithProductForUser(int $userId, int $productId): array
    {
        $orders = Order::where('user_id', $userId)
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->with(['items', 'items.product'])
            ->get();

        return $this->mapCollection($orders);
    }

    /**
     * Count total orders for a user
     */
    public function countTotalOrdersForUser(int $userId): int
    {
        return Order::where('user_id', $userId)->count();
    }

    /**
     * Get total amount spent by a user
     */
    public function getTotalSpentForUser(int $userId): float
    {
        return Order::where('user_id', $userId)
            ->whereNotIn('status', ['cancelled'])
            ->whereIn('payment_status', ['completed', 'succeeded', 'paid'])
            ->sum('total');
    }

    /**
     * Helper method to map collection of orders to array
     */
    private function mapCollection($orders): array
    {
        $result = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }

            // ✅ CORREGIDO: Deserialización segura de shipping_data SIN logs
            $shippingData = null;
            if ($order->shipping_data) {
                if (is_string($order->shipping_data)) {
                    $shippingData = json_decode($order->shipping_data, true);
                } elseif (is_array($order->shipping_data)) {
                    $shippingData = $order->shipping_data;
                }
            }

            $result[] = new OrderEntity(
                $order->user_id,
                $order->seller_id,
                $items,
                $order->total,
                $order->status,
                $order->payment_id,
                $order->payment_method,
                $order->payment_status,
                $shippingData,
                $order->order_number,
                $order->id,
                new \DateTime($order->created_at),
                new \DateTime($order->updated_at)
            );
        }

        return $result;
    }

    /**
     * Create order from webhook data
     * BEST PRACTICE: Dedicated method for webhook-created orders
     */
    public function createFromWebhook(array $orderData): OrderEntity
    {
        return DB::transaction(function () use ($orderData) {
            Log::info('Creating order from webhook', [
                'order_id' => $orderData['id'],
                'amount' => $orderData['payment']['amount'] ?? 'unknown',
            ]);

            // Create main order record
            $order = new Order;
            $order->id = $orderData['id']; // Use the order_id from payment
            $order->user_id = $orderData['user_id']; // Use the user_id from webhook logic
            $order->seller_id = $orderData['seller_id'] ?? null; // 🔧 CORREGIDO: usar seller_id del orderData
            $order->order_number = $orderData['id']; // Use same as ID for Deuna orders
            $order->status = $orderData['status'];
            $order->payment_status = $orderData['payment_status'];
            $order->payment_method = $orderData['payment']['method'];
            $order->payment_id = $orderData['payment']['payment_id'] ?? null; // 🔧 AGREGADO: payment_id
            $order->subtotal_products = $orderData['totals']['subtotal'];
            $order->iva_amount = $orderData['totals']['tax'];
            $order->shipping_cost = $orderData['totals']['shipping'];
            $order->total = $orderData['totals']['total'];

            // 🔧 NUEVO: Guardar detalles completos de pricing
            if (isset($orderData['totals']['subtotal_original'])) {
                $order->original_total = $orderData['totals']['subtotal_original'];
            }
            if (isset($orderData['totals']['seller_discounts'])) {
                $order->seller_discount_savings = $orderData['totals']['seller_discounts'];
            }
            if (isset($orderData['totals']['volume_discounts'])) {
                $order->volume_discount_savings = $orderData['totals']['volume_discounts'];
            }
            if (isset($orderData['totals']['total_discounts'])) {
                $order->total_discounts = $orderData['totals']['total_discounts'];
            }
            if (isset($orderData['totals']['volume_discount_percentage'])) {
                $order->volume_discounts_applied = $orderData['totals']['volume_discount_percentage'] > 0;
            }
            if (isset($orderData['totals']['free_shipping'])) {
                $order->free_shipping = $orderData['totals']['free_shipping'];
            }
            if (isset($orderData['totals']['free_shipping_threshold'])) {
                $order->free_shipping_threshold = $orderData['totals']['free_shipping_threshold'];
            }

            // Guardar breakdown completo de pricing para detalles
            $order->pricing_breakdown = json_encode($orderData['totals']);

            // 🔧 CORREGIDO: Store shipping info from customer data (formato igual a Datafast)
            $order->shipping_data = json_encode([
                'first_name' => explode(' ', $orderData['customer']['name'])[0] ?? '',
                'last_name' => explode(' ', $orderData['customer']['name'], 2)[1] ?? '',
                'email' => $orderData['customer']['email'],
                'phone' => $orderData['customer']['phone'],
                'address' => 'Dirección del checkout de DeUna', // Mejorar mensaje
                'city' => 'Ciudad',
                'state' => 'Estado',
                'postal_code' => '00000',
                'country' => 'EC',
            ]);

            // 🔧 CORREGIDO: Store payment_details with proper backend timestamp
            $order->payment_details = json_encode($orderData['payment_details'] ?? [
                'created_via' => $orderData['created_via'],
                'payment_id' => $orderData['payment']['payment_id'],
                'transaction_id' => $orderData['payment']['transaction_id'],
                'processed_at' => now()->format('Y-m-d H:i:s'),
            ]);

            $order->save();

            // Create order items
            foreach ($orderData['items'] as $itemData) {
                $orderItem = new OrderItem;
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $itemData['product_id'];
                $orderItem->quantity = $itemData['quantity'];
                $orderItem->price = $itemData['price']; // Precio final con descuentos
                $orderItem->subtotal = $itemData['subtotal'];
                $orderItem->product_name = $itemData['name'];

                // 🚨 FIX CRÍTICO: Obtener seller_id del producto para que createSellerOrdersForDeuna() funcione
                if ($itemData['product_id']) {
                    try {
                        $product = \App\Models\Product::find($itemData['product_id']);
                        if ($product) {
                            $orderItem->original_price = $product->price; // Precio original sin descuentos
                            $orderItem->seller_id = $product->seller_id; // ✅ CRÍTICO: Agregar seller_id
                        } else {
                            Log::error('❌ DEUNA WEBHOOK: Product not found when creating order items', [
                                'product_id' => $itemData['product_id'],
                                'order_id' => $order->id
                            ]);
                            $orderItem->original_price = $itemData['price'];
                            $orderItem->seller_id = null; // Fallback a null si no encontramos el producto
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ DEUNA WEBHOOK: Error getting product seller_id', [
                            'product_id' => $itemData['product_id'],
                            'error' => $e->getMessage(),
                            'order_id' => $order->id
                        ]);
                        // Si falla, usar el precio del item como original
                        $orderItem->original_price = $itemData['price'];
                        $orderItem->seller_id = null; // Fallback a null
                    }
                } else {
                    $orderItem->original_price = $itemData['price'];
                    $orderItem->seller_id = null; // No hay producto, no hay seller
                }

                $orderItem->save();
            }

            Log::info('Order created successfully from webhook', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ]);

            // Convert back to entity
            return $this->findById($order->id);
        });
    }

    /**
     * Find or create user by email for webhook orders
     */
    private function findOrCreateUserByEmail(string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Create minimal user for webhook order
            $user = new User;
            $user->email = $email;
            $user->name = 'DeUna Customer'; // Will be updated when user registers
            $user->password = bcrypt(\Str::random(32)); // Random password
            $user->email_verified_at = now(); // Auto-verify for payment customers
            $user->save();

            Log::info('Created new user from webhook order', [
                'user_id' => $user->id,
                'email' => $email,
            ]);
        }

        return $user->id;
    }
}
