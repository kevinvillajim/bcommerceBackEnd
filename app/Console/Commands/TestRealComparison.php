<?php

namespace App\Console\Commands;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Http\Controllers\DatafastController;
use App\Models\Product;
use App\Models\User;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use Illuminate\Console\Command;
use ReflectionClass;

class TestRealComparison extends Command
{
    protected $signature = 'test:real-comparison {userId} {productId} {quantity=1}';

    protected $description = 'Test REAL comparando DATAFAST vs DEUNA con los mismos datos exactos';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');
        $quantity = (int) $this->argument('quantity');

        $this->info('🔬 === TEST REAL: DATAFAST vs DEUNA ===');
        $this->info("Usuario: $userId, Producto: $productId, Cantidad: $quantity");

        // 1. Validaciones iniciales
        $user = User::find($userId);
        $product = Product::find($productId);

        if (! $user || ! $product) {
            $this->error('❌ Usuario o producto no válido');

            return 1;
        }

        $this->info("\n📋 DATOS BASE:");
        $this->table(['Campo', 'Valor'], [
            ['Usuario', $user->name],
            ['Email', $user->email],
            ['Producto', $product->name],
            ['Precio base', '$'.$product->price],
            ['Descuento seller', $product->discount_percentage.'%'],
            ['Seller ID', $product->seller_id],
        ]);

        // 2. Preparar carrito común
        $this->info("\n🛒 PREPARANDO CARRITO COMÚN...");
        $this->prepareCommonCart($userId, $productId, $quantity);

        // 3. Test DATAFAST (usando el método fallback que sabemos funciona)
        $this->info("\n💳 EJECUTANDO DATAFAST...");
        $datafastResult = $this->testDatafast($user, $productId, $quantity);

        // 4. Test DEUNA (usando ProcessCheckoutUseCase directamente)
        $this->info("\n🏦 EJECUTANDO DEUNA...");
        $deunaResult = $this->testDeuna($user, $productId, $quantity);

        // 5. Comparación REAL
        $this->info("\n📊 COMPARACIÓN REAL:");
        $this->compareResults($datafastResult, $deunaResult);

        return 0;
    }

    /**
     * Preparar carrito común para ambos tests
     */
    private function prepareCommonCart(int $userId, int $productId, int $quantity): void
    {
        $cartRepository = app(ShoppingCartRepositoryInterface::class);
        $cart = $cartRepository->findByUserId($userId);

        if (! $cart) {
            $cartEntity = new \App\Domain\Entities\ShoppingCartEntity(0, $userId);
            $cart = $cartRepository->save($cartEntity);
        }

        // Limpiar carrito
        $cartRepository->clearCart($cart->getId());

        // Agregar producto
        $product = Product::find($productId);
        $cartItem = new CartItemEntity(
            0,
            $cart->getId(),
            $productId,
            $quantity,
            $product->price,
            $product->price * $quantity
        );

        $cartRepository->addItem($cart->getId(), $cartItem);

        $this->info("✅ Carrito preparado: $quantity x {$product->name}");
    }

    /**
     * Test DATAFAST usando el método fallback real
     */
    private function testDatafast($user, $productId, $quantity): array
    {
        try {
            $cartRepository = app(ShoppingCartRepositoryInterface::class);
            $cart = $cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                return ['success' => false, 'message' => 'Carrito vacío'];
            }

            // Usar el método fallback de DATAFAST (que acabamos de arreglar)
            $controller = app(DatafastController::class);
            $reflector = new ReflectionClass($controller);
            $method = $reflector->getMethod('createOrderDirectly');
            $method->setAccessible(true);

            $paymentData = [
                'method' => 'datafast',
                'transaction_id' => 'TEST_DF_'.time(),
                'payment_id' => 'TEST_PAYMENT_DF_'.uniqid(),
                'status' => 'completed',
                'amount' => 6.90,
            ];

            $shippingData = [
                'address' => 'Test Address DATAFAST',
                'city' => 'Test City',
                'state' => 'Test State',
                'country' => 'EC',
            ];

            $order = $method->invoke($controller, $cart, $user, 6.90, $paymentData, $shippingData);

            $this->info('✅ DATAFAST creó orden: '.$order->getId());

            // ✅ CRÍTICO: Recargar la orden desde el repository para obtener valores correctos
            $repository = app(\App\Domain\Repositories\OrderRepositoryInterface::class);
            $reloadedOrder = $repository->findById($order->getId());

            return [
                'success' => true,
                'order_id' => $reloadedOrder->getId(),
                'total' => $reloadedOrder->getTotal(),
                'original_total' => $reloadedOrder->getOriginalTotal(),
                'subtotal_products' => $reloadedOrder->getSubtotalProducts(),
                'iva_amount' => $reloadedOrder->getIvaAmount(),
                'shipping_cost' => $reloadedOrder->getShippingCost(),
                'total_discounts' => $reloadedOrder->getTotalDiscounts(),
                'seller_discount_savings' => $reloadedOrder->getSellerDiscountSavings(),
                'volume_discount_savings' => $reloadedOrder->getVolumeDiscountSavings(),
            ];

        } catch (\Exception $e) {
            $this->error('❌ DATAFAST Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test DEUNA usando ProcessCheckoutUseCase directamente
     */
    private function testDeuna($user, $productId, $quantity): array
    {
        try {
            $cartRepository = app(ShoppingCartRepositoryInterface::class);
            $cart = $cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                return ['success' => false, 'message' => 'Carrito vacío'];
            }

            // Preparar datos igual que DEUNA
            $cartItems = [];
            foreach ($cart->getItems() as $item) {
                $cartItems[] = [
                    'product_id' => $item->getProductId(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            $paymentData = [
                'method' => 'deuna',
                'transaction_id' => 'TEST_DN_'.time(),
                'payment_id' => 'TEST_PAYMENT_DN_'.uniqid(),
                'status' => 'completed',
                'amount' => 6.90,
            ];

            $shippingData = [
                'address' => 'Test Address DEUNA',
                'city' => 'Test City',
                'state' => 'Test State',
                'country' => 'EC',
            ];

            // Usar ProcessCheckoutUseCase directamente
            $checkoutUseCase = app(ProcessCheckoutUseCase::class);
            $billingData = $shippingData; // Para commands, billing = shipping
            $result = $checkoutUseCase->execute(
                $user->id,
                $paymentData,
                $shippingData,
                $billingData,
                $cartItems,
                null, // seller_id
                null, // discount_code
                null  // calculatedTotals
            );

            $order = $result['order'];

            $this->info('✅ DEUNA creó orden: '.$order->getId());

            return [
                'success' => true,
                'order_id' => $order->getId(),
                'total' => $order->getTotal(),
                'original_total' => $order->getOriginalTotal(),
                'subtotal_products' => $order->getSubtotalProducts(),
                'iva_amount' => $order->getIvaAmount(),
                'shipping_cost' => $order->getShippingCost(),
                'total_discounts' => $order->getTotalDiscounts(),
                'seller_discount_savings' => $order->getSellerDiscountSavings(),
                'volume_discount_savings' => $order->getVolumeDiscountSavings(),
            ];

        } catch (\Exception $e) {
            $this->error('❌ DEUNA Error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Comparar resultados reales
     */
    private function compareResults(array $datafast, array $deuna): void
    {
        if (! $datafast['success'] || ! $deuna['success']) {
            $this->error('❌ Uno o ambos tests fallaron');
            $this->error('DATAFAST: '.($datafast['message'] ?? 'OK'));
            $this->error('DEUNA: '.($deuna['message'] ?? 'OK'));

            return;
        }

        // Comparación campo por campo
        $fields = [
            'total',
            'original_total',
            'subtotal_products',
            'iva_amount',
            'shipping_cost',
            'total_discounts',
            'seller_discount_savings',
            'volume_discount_savings',
        ];

        $tableData = [];
        $allIdentical = true;

        foreach ($fields as $field) {
            $datafastValue = $datafast[$field] ?? 0;
            $deunaValue = $deuna[$field] ?? 0;
            $identical = abs($datafastValue - $deunaValue) < 0.01;

            if (! $identical) {
                $allIdentical = false;
            }

            $tableData[] = [
                ucfirst(str_replace('_', ' ', $field)),
                '$'.number_format($datafastValue, 2),
                '$'.number_format($deunaValue, 2),
                $identical ? '✅' : '❌',
            ];
        }

        $this->table(['Campo', 'DATAFAST', 'DEUNA', 'Igual'], $tableData);

        // Resultado final
        if ($allIdentical) {
            $this->info("\n🎉 ✅ RESULTADO: AMBOS SISTEMAS SON IDÉNTICOS");
            $this->info('▪️ DATAFAST Orden ID: '.$datafast['order_id']);
            $this->info('▪️ DEUNA Orden ID: '.$deuna['order_id']);
            $this->info('▪️ Ambos generaron exactamente los mismos breakdowns');
        } else {
            $this->error("\n💥 ❌ RESULTADO: LOS SISTEMAS NO SON IDÉNTICOS");
            $this->error('Hay diferencias en los cálculos - el problema NO está resuelto');
        }

        // Verificar en base de datos
        $this->info("\n🔍 VERIFICACIÓN EN BASE DE DATOS:");
        $this->call('debug:inspect-order', ['orderId' => $datafast['order_id']]);
        $this->info("\n".str_repeat('=', 50));
        $this->call('debug:inspect-order', ['orderId' => $deuna['order_id']]);
    }
}
