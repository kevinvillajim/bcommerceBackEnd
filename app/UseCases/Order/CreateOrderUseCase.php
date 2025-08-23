<?php

namespace App\UseCases\Order;

use App\Domain\Entities\OrderEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Facades\Log;

class CreateOrderUseCase
{
    private OrderRepositoryInterface $orderRepository;

    private ProductRepositoryInterface $productRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * ✅ CORREGIDO: Ejecutar creación de orden con soporte para descuentos por volumen
     */
    public function execute(array $orderData): OrderEntity
    {
        try {
            Log::info('🏗️ CreateOrderUseCase: Creando orden con pricing detallado', [
                'user_id' => $orderData['user_id'],
                'total' => $orderData['total'],
                'original_total' => $orderData['original_total'] ?? null,
                'volume_discount_savings' => $orderData['volume_discount_savings'] ?? 0,
                'volume_discounts_applied' => $orderData['volume_discounts_applied'] ?? false,
                'subtotal_products' => $orderData['subtotal_products'] ?? null,
                'iva_amount' => $orderData['iva_amount'] ?? null,
                'shipping_cost' => $orderData['shipping_cost'] ?? null,
                'total_discounts' => $orderData['total_discounts'] ?? 0.0, // ✅ AGREGADO PARA DEBUG
            ]);

            // Validar datos mínimos
            if (! isset($orderData['user_id'])) {
                throw new \Exception('user_id es requerido');
            }

            // ✅ PERMITIR órdenes sin items (para órdenes principales que son contenedores)
            $items = $orderData['items'] ?? [];

            // Calcular total si no está definido
            if (! isset($orderData['total'])) {
                $total = 0;
                foreach ($items as $item) {
                    $total += $item['subtotal'] ?? ($item['price'] * $item['quantity']);
                }
                $orderData['total'] = $total;
            }

            // ✅ VALIDAR solo si hay items
            if (! empty($items)) {
                foreach ($items as $item) {
                    if (! isset($item['product_id']) || ! isset($item['quantity']) || ! isset($item['price'])) {
                        throw new \Exception('Datos de item incompletos: se requiere product_id, quantity y price');
                    }
                }
            }

            // ✅ CREAR LA ENTIDAD DE ORDEN CON TODOS LOS CAMPOS DE PRICING
            $order = OrderEntity::create(
                $orderData['user_id'],
                $orderData['seller_id'] ?? null,
                $items,
                $orderData['total'],
                $orderData['status'] ?? 'pending',
                $orderData['shipping_data'] ?? null,
                // ✅ CAMPOS DE DESCUENTOS POR VOLUMEN
                $orderData['original_total'] ?? null,
                $orderData['volume_discount_savings'] ?? 0.0,
                $orderData['volume_discounts_applied'] ?? false,
                // 🔧 AGREGADO: Descuentos del vendedor
                $orderData['seller_discount_savings'] ?? 0.0,
                // ✅ CAMPOS DE PRICING DETALLADO - CORREGIDOS LOS FALLBACKS
                $orderData['subtotal_products'] ?? 0.0,    // ✅ CORREGIDO: 0.0 en lugar de null
                $orderData['iva_amount'] ?? 0.0,           // ✅ CORREGIDO: 0.0 en lugar de null
                $orderData['shipping_cost'] ?? 0.0,       // ✅ CORREGIDO: 0.0 en lugar de null
                $orderData['total_discounts'] ?? 0.0,     // ✅ CORREGIDO: 0.0 en lugar de null - ESTE ERA EL ERROR
                $orderData['free_shipping'] ?? false,     // ✅ CORREGIDO: false en lugar de null
                $orderData['free_shipping_threshold'] ?? null,  // Este sí puede ser null
                $orderData['pricing_breakdown'] ?? null,        // Este sí puede ser null
                // ✅ NUEVOS: Campos de código de descuento de feedback
                $orderData['feedback_discount_code'] ?? null,
                $orderData['feedback_discount_amount'] ?? 0.0,
                $orderData['feedback_discount_percentage'] ?? 0.0,
                // 🔧 AGREGADO: payment_details
                $orderData['payment_details'] ?? null
            );

            // Guardar la orden en la base de datos usando el método existente
            $savedOrder = $this->orderRepository->save($order);

            Log::info('✅ CreateOrderUseCase: Orden creada exitosamente', [
                'order_id' => $savedOrder->getId(),
                'order_number' => $savedOrder->getOrderNumber(),
                'final_total' => $savedOrder->getTotal(),
                'original_total' => $savedOrder->getOriginalTotal(),
                'volume_savings' => $savedOrder->getVolumeDiscountSavings(),
                'total_discounts' => $savedOrder->getTotalDiscounts(), // ✅ AGREGADO
                'discounts_applied' => $savedOrder->getVolumeDiscountsApplied(),
            ]);

            return $savedOrder;

        } catch (\Exception $e) {
            Log::error('❌ CreateOrderUseCase: Error creando orden', [
                'user_id' => $orderData['user_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Error al crear la orden: '.$e->getMessage());
        }
    }
}
