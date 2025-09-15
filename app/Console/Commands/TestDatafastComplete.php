<?php

namespace App\Console\Commands;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Models\DatafastPayment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDatafastComplete extends Command
{
    protected $signature = 'test:datafast-complete {userId} {productId} {quantity=1}';

    protected $description = 'Test completo del flujo DATAFAST - simula todo el proceso';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');
        $quantity = (int) $this->argument('quantity');

        $this->info('🧪 === TEST COMPLETO DATAFAST ===');
        $this->info("Usuario: $userId, Producto: $productId, Cantidad: $quantity");

        try {
            // NO usar DB::beginTransaction() aquí porque ProcessCheckoutUseCase maneja sus propias transacciones

            // 1. Validaciones básicas
            $user = User::find($userId);
            $product = Product::find($productId);

            if (! $user || ! $product) {
                $this->error('❌ Usuario o producto no válido');

                return 1;
            }

            $this->info("\n📋 1. DATOS BÁSICOS:");
            $this->table(['Campo', 'Valor'], [
                ['Usuario', $user->name],
                ['Email', $user->email],
                ['Producto', $product->name],
                ['Precio base', $product->price],
                ['Descuento seller', $product->discount_percentage ?? 0],
                ['Seller ID', $product->seller_id],
            ]);

            // 2. Preparar carrito (simular)
            $cartRepository = app(ShoppingCartRepositoryInterface::class);
            $cart = $cartRepository->findByUserId($userId);

            if (! $cart) {
                // Crear carrito temporal si no existe
                $cartEntity = new \App\Domain\Entities\ShoppingCartEntity(0, $userId);
                $cart = $cartRepository->save($cartEntity);
            }

            // Limpiar carrito y agregar producto
            $cartRepository->clearCart($cart->getId());

            // Crear CartItemEntity para agregar al carrito
            $cartItem = new \App\Domain\Entities\CartItemEntity(
                0, // id
                $cart->getId(), // cart_id
                $productId,
                $quantity,
                $product->price,
                $product->price * $quantity // subtotal
            );

            $cartRepository->addItem($cart->getId(), $cartItem);

            // Refrescar carrito
            $cart = $cartRepository->findByUserId($userId);

            $this->info("\n🛒 2. CARRITO PREPARADO:");
            $this->table(['Campo', 'Valor'], [
                ['Items en carrito', count($cart->getItems())],
                ['Total del carrito', $cart->getTotal()],
            ]);

            // 3. Simular datos de pago DATAFAST
            $paymentData = [
                'method' => 'datafast',
                'amount' => 999.99, // Se calculará dinámicamente
                'customer' => [
                    'given_name' => 'Test',
                    'surname' => 'Usuario',
                    'email' => $user->email,
                    'phone' => '0999999999',
                    'doc_id' => '1234567890',
                ],
            ];

            $shippingData = [
                'first_name' => 'Test',
                'last_name' => 'Usuario',
                'email' => $user->email,
                'phone' => '0999999999',
                'address' => 'Calle Test 123',
                'city' => 'Quito',
                'state' => 'Pichincha',
                'country' => 'Ecuador',
                'postal_code' => '170150',
            ];

            // 4. Crear transacción DATAFAST
            $datafastPayment = DatafastPayment::create([
                'user_id' => $userId,
                'amount' => 999.99, // Se actualizará
                'currency' => 'USD',
                'status' => 'pending',
                'customer_data' => $paymentData['customer'],
                'shipping_data' => $shippingData,
                'created_at' => now(),
            ]);

            $this->info("\n💳 3. TRANSACCIÓN DATAFAST CREADA:");
            $this->table(['Campo', 'Valor'], [
                ['Transaction ID', $datafastPayment->id],
                ['Status', $datafastPayment->status],
                ['Amount', $datafastPayment->amount],
            ]);

            // 5. Simular respuesta exitosa de DATAFAST
            $datafastResponse = [
                'success' => true,
                'payment_id' => 'DATAFAST_TEST_'.uniqid(),
                'result_code' => '0', // Código de éxito
                'transaction_id' => $datafastPayment->id,
                'amount' => 6.90, // Valor real calculado
            ];

            // 6. Llamar al endpoint de confirmación DATAFAST
            $this->info("\n⚡ 4. SIMULANDO CONFIRMACIÓN DATAFAST...");

            // Simular el proceso del DatafastController::confirmPayment
            $controller = app(\App\Http\Controllers\DatafastController::class);

            // Preparar request simulado
            $requestData = [
                'transaction_id' => $datafastPayment->id,
                'payment_id' => $datafastResponse['payment_id'],
                'result_code' => $datafastResponse['result_code'],
                'amount' => $datafastResponse['amount'],
            ];

            // Simular el método confirmPayment
            $result = $this->simulateDatafastConfirmation($requestData, $datafastPayment);

            if ($result['success']) {
                $this->info('✅ CONFIRMACIÓN DATAFAST EXITOSA');

                $order = $result['order'];

                $this->info("\n🎯 5. ORDEN CREADA - VERIFICACIÓN COMPLETA:");
                $this->table(['Campo', 'Valor'], [
                    ['Order ID', $order->getId()],
                    ['Order Number', $order->getOrderNumber()],
                    ['Total', $order->getTotal()],
                    ['Original Total', $order->getOriginalTotal()],
                    ['Subtotal Products', $order->getSubtotalProducts()],
                    ['IVA Amount', $order->getIvaAmount()],
                    ['Shipping Cost', $order->getShippingCost()],
                    ['Total Discounts', $order->getTotalDiscounts()],
                    ['Seller Discounts', $order->getSellerDiscountSavings()],
                    ['Volume Discounts', $order->getVolumeDiscountSavings()],
                ]);

                // 6. Verificar en base de datos
                $this->info("\n🔍 6. VERIFICACIÓN EN BASE DE DATOS:");
                $this->call('debug:inspect-order', ['orderId' => $order->getId()]);

                // 7. Verificar seller_orders
                $sellerOrders = DB::table('seller_orders')
                    ->where('order_id', $order->getId())
                    ->get();

                $this->info("\n👨‍💼 7. SELLER ORDERS:");
                if ($sellerOrders->count() > 0) {
                    foreach ($sellerOrders as $so) {
                        $this->table(['Campo', 'Valor'], [
                            ['Seller Order ID', $so->id],
                            ['Seller ID', $so->seller_id],
                            ['Total', $so->total],
                            ['Payment Status', $so->payment_status ?? 'N/A'],
                            ['Payment Method', $so->payment_method ?? 'N/A'],
                        ]);
                    }
                } else {
                    $this->warn('⚠️ No se encontraron seller_orders');
                }

                // 8. Verificar order_items
                $orderItems = DB::table('order_items as oi')
                    ->leftJoin('products as p', 'oi.product_id', '=', 'p.id')
                    ->where('oi.order_id', $order->getId())
                    ->select('oi.*', 'p.name as product_name')
                    ->get();

                $this->info("\n📦 8. ORDER ITEMS:");
                foreach ($orderItems as $item) {
                    $this->table(['Campo', 'Valor'], [
                        ['Product', $item->product_name],
                        ['Quantity', $item->quantity],
                        ['Price', $item->price],
                        ['Original Price', $item->original_price ?? 'N/A'],
                        ['Subtotal', $item->subtotal],
                        ['Volume Savings', $item->volume_savings ?? 'N/A'],
                        ['Seller Order ID', $item->seller_order_id ?? 'N/A'],
                    ]);
                }

                $this->info("\n🎉 ✅ TEST DATAFAST COMPLETADO EXITOSAMENTE");

                return 0;

            } else {
                $this->error('❌ Error en confirmación DATAFAST: '.$result['message']);

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('💥 ERROR CRÍTICO: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }
    }

    /**
     * Simula el proceso de confirmación DATAFAST
     */
    private function simulateDatafastConfirmation(array $requestData, $datafastPayment): array
    {
        try {
            // Simular el proceso interno del DatafastController
            $user = User::find($datafastPayment->user_id);
            $cartRepository = app(ShoppingCartRepositoryInterface::class);
            $cart = $cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                return ['success' => false, 'message' => 'Carrito vacío'];
            }

            // Preparar items del carrito
            $cartItems = [];
            foreach ($cart->getItems() as $item) {
                $cartItems[] = [
                    'product_id' => $item->getProductId(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            // Usar ProcessCheckoutUseCase
            $checkoutUseCase = app(\App\UseCases\Checkout\ProcessCheckoutUseCase::class);
            $shippingData = $datafastPayment->shipping_data ?? [
                'first_name' => 'Test',
                'last_name' => 'Usuario',
                'email' => $user->email,
                'phone' => '0999999999',
                'address' => 'Calle Test 123',
                'city' => 'Quito',
                'state' => 'Pichincha',
                'country' => 'Ecuador',
            ];
            $billingData = $shippingData; // Para commands, billing = shipping
            $checkoutResult = $checkoutUseCase->execute(
                $user->id,
                [
                    'method' => 'datafast',
                    'payment_id' => $requestData['payment_id'],
                ],
                $shippingData,
                $billingData,
                $cartItems,
                null, // seller_id (se detecta automáticamente)
                null, // discount_code
                null  // calculatedTotals (dejar que PricingCalculatorService calcule)
            );

            // Marcar pago DATAFAST como completado
            $datafastPayment->markAsCompleted(
                $requestData['payment_id'],
                $requestData['result_code'],
                'Test completado exitosamente'
            );

            // Vincular orden con transacción DATAFAST
            $datafastPayment->update(['order_id' => $checkoutResult['order']->getId()]);

            return [
                'success' => true,
                'order' => $checkoutResult['order'],
                'seller_orders' => $checkoutResult['seller_orders'] ?? [],
                'payment' => $checkoutResult['payment'] ?? [],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }
}
