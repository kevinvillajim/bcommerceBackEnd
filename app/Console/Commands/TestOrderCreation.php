<?php

namespace App\Console\Commands;

use App\Domain\Services\PricingCalculatorService;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Console\Command;

class TestOrderCreation extends Command
{
    protected $signature = 'debug:test-order-creation {userId} {productId}';

    protected $description = 'Testa solo la creación de orden sin transacciones';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');

        $this->info('=== TEST CREACIÓN DE ORDEN ===');

        // 1. Usar PricingCalculatorService
        $cartItems = [
            [
                'product_id' => (int) $productId,
                'quantity' => 1,
            ],
        ];

        $pricingService = app(PricingCalculatorService::class);
        $pricingResult = $pricingService->calculateCartTotals($cartItems, (int) $userId, null);

        $this->info("\n1. VALORES DE PRICINGCALCULATORSERVICE:");
        $this->table(['Campo', 'Valor'], [
            ['subtotal_original', $pricingResult['subtotal_original']],
            ['subtotal_with_discounts', $pricingResult['subtotal_with_discounts']],
            ['iva_amount', $pricingResult['iva_amount']],
            ['shipping_cost', $pricingResult['shipping_cost']],
            ['total_discounts', $pricingResult['total_discounts']],
            ['final_total', $pricingResult['final_total']],
        ]);

        // 2. Preparar datos para OrderEntity como ProcessCheckoutUseCase
        $orderData = [
            'user_id' => (int) $userId,
            'total' => $pricingResult['final_total'],
            'original_total' => $pricingResult['subtotal_original'],
            'subtotal_products' => $pricingResult['subtotal_with_discounts'],
            'iva_amount' => $pricingResult['iva_amount'],
            'shipping_cost' => $pricingResult['shipping_cost'],
            'total_discounts' => $pricingResult['total_discounts'],
            'seller_discount_savings' => $pricingResult['seller_discounts'],
            'volume_discount_savings' => $pricingResult['volume_discounts'],
            'volume_discounts_applied' => $pricingResult['volume_discounts'] > 0,
            'free_shipping' => $pricingResult['free_shipping'],
            'free_shipping_threshold' => $pricingResult['free_shipping_threshold'],
            'pricing_breakdown' => $pricingResult,
            'items' => [],
            'status' => 'testing',
            'shipping_data' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
            ],
        ];

        $this->info("\n2. DATOS PREPARADOS PARA ORDERDATA:");
        $this->table(['Campo', 'Valor'], [
            ['total', $orderData['total']],
            ['original_total', $orderData['original_total']],
            ['subtotal_products', $orderData['subtotal_products']],
            ['iva_amount', $orderData['iva_amount']],
            ['shipping_cost', $orderData['shipping_cost']],
            ['total_discounts', $orderData['total_discounts']],
        ]);

        // 3. Crear orden usando CreateOrderUseCase
        try {
            $createOrderUseCase = app(CreateOrderUseCase::class);
            $createdOrder = $createOrderUseCase->execute($orderData);

            $this->info("\n3. ORDEN CREADA - VALORES EN ORDERENTITY:");
            $this->table(['Campo', 'Valor'], [
                ['id', $createdOrder->getId()],
                ['total', $createdOrder->getTotal()],
                ['original_total', $createdOrder->getOriginalTotal()],
                ['subtotal_products', $createdOrder->getSubtotalProducts()],
                ['iva_amount', $createdOrder->getIvaAmount()],
                ['shipping_cost', $createdOrder->getShippingCost()],
                ['total_discounts', $createdOrder->getTotalDiscounts()],
            ]);

            // 4. Verificar en base de datos
            $this->info("\n4. VERIFICACIÓN EN BASE DE DATOS:");
            $this->call('debug:inspect-order', ['orderId' => $createdOrder->getId()]);

        } catch (\Exception $e) {
            $this->error('Error creando orden: '.$e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
