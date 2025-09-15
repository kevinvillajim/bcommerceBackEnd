<?php

namespace App\Console\Commands;

use App\Domain\Services\PricingCalculatorService;
use App\Models\Product;
use App\Models\User;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use Illuminate\Console\Command;

class TestDatafastFlow extends Command
{
    protected $signature = 'debug:test-datafast-flow {userId} {productId} {quantity=1}';

    protected $description = 'Testa el flujo completo de DATAFAST para debugging';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');
        $quantity = (int) $this->argument('quantity');

        $this->info('=== TEST FLUJO DATAFAST ===');
        $this->info("Usuario: $userId, Producto: $productId, Cantidad: $quantity");

        // 1. Verificar usuario y producto
        $user = User::find($userId);
        $product = Product::find($productId);

        if (! $user) {
            $this->error("Usuario $userId no encontrado");

            return;
        }

        if (! $product) {
            $this->error("Producto $productId no encontrado");

            return;
        }

        $this->info("\n1. DATOS BÁSICOS:");
        $this->table(['Campo', 'Valor'], [
            ['usuario', $user->name],
            ['producto', $product->name],
            ['precio_base', $product->price],
            ['descuento_seller', $product->discount_percentage ?? 0],
            ['seller_id', $product->seller_id],
        ]);

        // 2. Simular items del carrito como lo hace DATAFAST
        $cartItems = [
            [
                'product_id' => $productId,
                'quantity' => $quantity,
            ],
        ];

        $this->info("\n2. TEST PRICINGCALCULATORSERVICE:");

        try {
            $pricingService = app(PricingCalculatorService::class);
            $pricingResult = $pricingService->calculateCartTotals($cartItems, $userId, null);

            $this->table(['Campo', 'Valor'], [
                ['subtotal_original', $pricingResult['subtotal_original']],
                ['subtotal_with_discounts', $pricingResult['subtotal_with_discounts']],
                ['seller_discounts', $pricingResult['seller_discounts']],
                ['volume_discounts', $pricingResult['volume_discounts']],
                ['total_discounts', $pricingResult['total_discounts']],
                ['shipping_cost', $pricingResult['shipping_cost']],
                ['iva_amount', $pricingResult['iva_amount']],
                ['final_total', $pricingResult['final_total']],
            ]);

        } catch (\Exception $e) {
            $this->error('Error en PricingCalculatorService: '.$e->getMessage());

            return;
        }

        // 3. Simular datos como DATAFAST
        $paymentData = [
            'method' => 'datafast',
            'amount' => $pricingResult['final_total'],
        ];

        $shippingData = [
            'first_name' => 'Test',
            'last_name' => 'Usuario',
            'email' => $user->email,
            'phone' => '0999999999',
            'address' => 'Test Address',
            'city' => 'Quito',
            'state' => 'Pichincha',
            'country' => 'Ecuador',
        ];

        $this->info("\n3. TEST PROCESSCHECKOUTUSECASE:");

        try {
            $checkoutUseCase = app(ProcessCheckoutUseCase::class);
            $billingData = $shippingData; // Para commands, billing = shipping
            $checkoutResult = $checkoutUseCase->execute(
                $userId,
                $paymentData,
                $shippingData,
                $billingData,
                $cartItems,
                null, // seller_id
                null, // discount_code
                null  // calculatedTotals (sin hardcodear)
            );

            $order = $checkoutResult['order'];

            $this->table(['Campo OrderEntity', 'Valor'], [
                ['id', $order->getId()],
                ['total', $order->getTotal()],
                ['original_total', $order->getOriginalTotal()],
                ['subtotal_products', $order->getSubtotalProducts()],
                ['iva_amount', $order->getIvaAmount()],
                ['shipping_cost', $order->getShippingCost()],
                ['total_discounts', $order->getTotalDiscounts()],
                ['volume_discounts', $order->getVolumeDiscountSavings()],
            ]);

        } catch (\Exception $e) {
            $this->error('Error en ProcessCheckoutUseCase: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return;
        }

        // 4. Verificar en base de datos
        $this->info("\n4. VERIFICACIÓN EN BASE DE DATOS:");

        $this->call('debug:inspect-order', ['orderId' => $order->getId()]);

        $this->info("\n✅ Test completado");
    }
}
