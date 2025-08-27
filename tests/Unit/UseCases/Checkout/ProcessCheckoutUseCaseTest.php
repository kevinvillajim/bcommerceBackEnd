<?php

namespace Tests\Unit\UseCases\Checkout;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\OrderEntity;
use App\Domain\Entities\ProductEntity;
use App\Domain\Entities\SellerOrderEntity;
use App\Domain\Entities\ShoppingCartEntity;
use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\SellerOrderRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Domain\Services\PricingCalculatorService;
use App\Services\ConfigurationService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessCheckoutUseCaseTest extends TestCase
{
    protected $cartRepository;
    protected $orderRepository;
    protected $productRepository;
    protected $sellerOrderRepository;
    protected $paymentGateway;
    protected $createOrderUseCase;
    protected $configService;
    protected $applyCartDiscountCodeUseCase;
    protected $pricingService;
    protected $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear todos los mocks necesarios
        $this->cartRepository = Mockery::mock(ShoppingCartRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->sellerOrderRepository = Mockery::mock(SellerOrderRepositoryInterface::class);
        $this->paymentGateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->createOrderUseCase = Mockery::mock(CreateOrderUseCase::class);
        $this->configService = Mockery::mock(ConfigurationService::class);
        $this->applyCartDiscountCodeUseCase = Mockery::mock(ApplyCartDiscountCodeUseCase::class);
        $this->pricingService = Mockery::mock(PricingCalculatorService::class);

        // Crear instancia del use case con todas las dependencias
        $this->useCase = new ProcessCheckoutUseCase(
            $this->cartRepository,
            $this->orderRepository,
            $this->productRepository,
            $this->sellerOrderRepository,
            $this->paymentGateway,
            $this->createOrderUseCase,
            $this->configService,
            $this->applyCartDiscountCodeUseCase,
            $this->pricingService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_processes_checkout_successfully()
    {
        $userId = 1;
        $cartId = 5;
        $orderId = 100;

        // Datos de pago y envío
        $paymentData = [
            'method' => 'credit_card',
            'card_number' => '4242424242424242',
            'card_expiry' => '12/25',
            'card_cvc' => '123',
        ];

        $shippingData = [
            'address' => 'Calle Falsa 123',
            'city' => 'Springfield',
            'state' => 'Springfield',
            'country' => 'US',
            'postal_code' => '12345',
            'phone' => '555-555-5555',
        ];

        // Simular items en el carrito
        $item1 = new CartItemEntity(1, $cartId, 100, 2, 50, 100);
        $item2 = new CartItemEntity(2, $cartId, 200, 1, 75, 75);
        $items = [$item1, $item2];

        // Simular carrito
        $cart = new ShoppingCartEntity(
            $cartId,
            $userId,
            $items,
            175
        );

        // Mock para obtener el carrito - se llama dos veces
        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->twice() // Una vez al inicio, otra vez para limpiarlo
            ->andReturn($cart);

        // Crear un user para el comprador
        \App\Models\User::factory()->create([
            'id' => 1,
            'name' => 'Test Buyer',
            'email' => 'buyer@test.com'
        ]);

        // Crear un user y seller para los productos
        $sellerUser = \App\Models\User::factory()->create([
            'id' => 2,
            'name' => 'Test Seller',
            'email' => 'seller@test.com'
        ]);

        \App\Models\Seller::factory()->create([
            'id' => 1,
            'user_id' => $sellerUser->id,
            'store_name' => 'Test Store',
            'status' => 'active'
        ]);

        // Crear categoría para los productos
        \App\Models\Category::factory()->create([
            'id' => 1,
            'name' => 'Test Category'
        ]);

        // Crear productos reales en la base de datos para stock validation
        \App\Models\Product::factory()->create([
            'id' => 100,
            'name' => 'Producto 1',
            'stock' => 10,
            'price' => 50.00,
            'user_id' => 2, // Seller user
            'seller_id' => 1,
            'category_id' => 1,
            'status' => 'active',
            'published' => true
        ]);

        \App\Models\Product::factory()->create([
            'id' => 200,
            'name' => 'Producto 2', 
            'stock' => 5,
            'price' => 75.00,
            'user_id' => 2, // Seller user
            'seller_id' => 1,
            'category_id' => 1,
            'status' => 'active',
            'published' => true
        ]);

        // Ya no necesitamos mocks del product repository porque la validación
        // se hace directamente con el modelo para el lock pesimista

        // Mock para el cálculo de precios con estructura exacta esperada
        $pricingResult = [
            'subtotal_original' => 175.00,
            'subtotal_with_discounts' => 175.00,
            'subtotal_after_coupon' => 175.00,
            'seller_discounts' => 0.00,
            'volume_discounts' => 0.00,
            'coupon_discount' => 0.00,
            'total_discounts' => 0.00,
            'iva_amount' => 27.00, // 15% de (175 + 5)
            'shipping_cost' => 5.00,
            'free_shipping' => false,
            'free_shipping_threshold' => 50.00,
            'final_total' => 207.00,
            'processed_items' => [
                [
                    'id' => 1,
                    'product_id' => 100,
                    'quantity' => 2,
                    'unit_price' => 50,
                    'total_price' => 100,
                    'subtotal' => 100,
                    'original_price' => 50,
                    'final_price' => 50,
                    'seller_discount_amount' => 0,
                    'volume_discount_amount' => 0,
                    'seller_id' => 1
                ],
                [
                    'id' => 2,
                    'product_id' => 200,
                    'quantity' => 1,
                    'unit_price' => 75,
                    'total_price' => 75,
                    'subtotal' => 75,
                    'original_price' => 75,
                    'final_price' => 75,
                    'seller_discount_amount' => 0,
                    'volume_discount_amount' => 0,
                    'seller_id' => 1
                ],
            ]
        ];

        $this->pricingService->shouldReceive('calculateCartTotals')
            ->once()
            ->andReturn($pricingResult);

        // Mock para el procesamiento del pago - con datos extendidos
        $expectedPaymentData = array_merge($paymentData, [
            'customer' => [
                'given_name' => 'Cliente',
                'surname' => 'De Prueba',
                'email' => 'test@example.com',
                'phone' => '555-555-5555',
                'doc_id' => '1234567890',
            ],
            'shipping' => $shippingData,
            'billing' => $shippingData,
        ]);

        $this->paymentGateway->shouldReceive('processPayment')
            ->with($expectedPaymentData, 207.00)
            ->once()
            ->andReturn([
                'success' => true,
                'transaction_id' => 'txn_123456',
                'message' => 'Payment successful'
            ]);

        // Crear una orden real en la base de datos
        $realOrder = \App\Models\Order::factory()->create([
            'id' => $orderId,
            'user_id' => $userId,
            'order_number' => 'ORD-2024-001',
            'status' => 'pending',
            'payment_status' => 'pending'
        ]);

        // Mock para la creación de la orden
        $order = Mockery::mock(OrderEntity::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getOrderNumber')->andReturn('ORD-2024-001');

        $this->createOrderUseCase->shouldReceive('execute')
            ->once()
            ->andReturn($order);

        // Mock para actualizar información de pago en la orden
        $this->orderRepository->shouldReceive('updatePaymentInfo')
            ->with($orderId, Mockery::subset([
                'payment_status' => 'completed',
                'payment_method' => 'credit_card',
                'status' => 'processing'
            ]))
            ->once();

        // Stock se actualiza directamente en el modelo, no a través del repository

        // Mock para crear seller order
        $sellerOrder = Mockery::mock(SellerOrderEntity::class);
        $sellerOrder->shouldReceive('getId')->andReturn(1);
        
        $this->sellerOrderRepository->shouldReceive('create')
            ->once()
            ->andReturn($sellerOrder);

        // Mock para limpiar el carrito (usa cart ID, no user ID)
        $this->cartRepository->shouldReceive('clearCart')
            ->with($cartId) // Cart ID = 5
            ->once();

        // Mock para obtener orden completada
        $this->orderRepository->shouldReceive('findById')
            ->with($orderId)
            ->once()
            ->andReturn($order);

        // Ejecutar el use case
        $result = $this->useCase->execute($userId, $paymentData, $shippingData);

        // Verificar el resultado
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order', $result);
        $this->assertEquals($order, $result['order']);
        $this->assertArrayHasKey('seller_orders', $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('pricing_info', $result);
    }

    #[Test]
    public function it_throws_exception_if_cart_is_empty()
    {
        $userId = 1;
        $paymentData = ['method' => 'credit_card'];
        $shippingData = [
            'address' => 'Test Address',
            'city' => 'Test City', 
            'state' => 'Test State',
            'country' => 'US',
            'postal_code' => '12345',
            'phone' => 'No phone provided'
        ];

        // Mock para carrito vacío
        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No hay items para procesar');

        $this->useCase->execute($userId, $paymentData, $shippingData);
    }

    #[Test]
    public function it_throws_exception_if_payment_fails()
    {
        $userId = 1;
        $cartId = 5;

        $paymentData = [
            'method' => 'credit_card',
            'card_number' => '4000000000000002', // Declined card
            'card_expiry' => '12/25',
            'card_cvc' => '123',
        ];

        $shippingData = [
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State', 
            'country' => 'US',
            'postal_code' => '12345',
            'phone' => 'No phone provided'
        ];

        // Crear datos de prueba similares al test principal
        \App\Models\User::factory()->create([
            'id' => 1,
            'name' => 'Test Buyer',
            'email' => 'buyer@test.com'
        ]);

        $sellerUser = \App\Models\User::factory()->create([
            'id' => 2,
            'name' => 'Test Seller',
            'email' => 'seller@test.com'
        ]);

        \App\Models\Seller::factory()->create([
            'id' => 1,
            'user_id' => $sellerUser->id,
            'store_name' => 'Test Store',
            'status' => 'active'
        ]);

        \App\Models\Category::factory()->create([
            'id' => 1,
            'name' => 'Test Category'
        ]);

        \App\Models\Product::factory()->create([
            'id' => 100,
            'name' => 'Producto',
            'stock' => 10,
            'price' => 50.00,
            'user_id' => 2,
            'seller_id' => 1,
            'category_id' => 1,
            'status' => 'active',
            'published' => true
        ]);

        // Simular carrito con items
        $item = new CartItemEntity(1, $cartId, 100, 1, 50, 50);
        $cart = new ShoppingCartEntity($cartId, $userId, [$item], 50);

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($cart);

        // Mock para cálculo de precios con estructura exacta esperada
        $pricingResult = [
            'subtotal_original' => 50.00,
            'subtotal_with_discounts' => 50.00,
            'subtotal_after_coupon' => 50.00,
            'seller_discounts' => 0.00,
            'volume_discounts' => 0.00,
            'coupon_discount' => 0.00,
            'total_discounts' => 0.00,
            'iva_amount' => 8.25, // 15% de (50 + 5)
            'shipping_cost' => 5.00,
            'free_shipping' => false,
            'free_shipping_threshold' => 50.00,
            'final_total' => 63.25,
            'processed_items' => [
                [
                    'id' => 1,
                    'product_id' => 100,
                    'quantity' => 1,
                    'unit_price' => 50,
                    'total_price' => 50,
                    'subtotal' => 50,
                    'original_price' => 50,
                    'final_price' => 50,
                    'seller_discount_amount' => 0,
                    'volume_discount_amount' => 0,
                    'seller_id' => 1
                ]
            ]
        ];

        $this->pricingService->shouldReceive('calculateCartTotals')
            ->once()
            ->andReturn($pricingResult);

        // Crear una orden real en la base de datos
        $realOrder = \App\Models\Order::factory()->create([
            'id' => 100,
            'user_id' => $userId,
            'order_number' => 'ORD-2024-FAIL',
            'status' => 'pending',
            'payment_status' => 'pending'
        ]);

        // Mock para CreateOrderUseCase - sí se ejecuta pero luego falla el pago
        $order = Mockery::mock(OrderEntity::class);
        $order->shouldReceive('getId')->andReturn(100);
        $order->shouldReceive('getOrderNumber')->andReturn('ORD-2024-FAIL');
        
        $this->createOrderUseCase->shouldReceive('execute')
            ->once()
            ->andReturn($order);

        // Mock para pago fallido - con estructura completa
        $expectedPaymentData = array_merge($paymentData, [
            'customer' => [
                'given_name' => 'Cliente',
                'surname' => 'De Prueba',
                'email' => 'test@example.com',
                'phone' => 'No phone provided',
                'doc_id' => '1234567890',
            ],
            'shipping' => $shippingData,
            'billing' => $shippingData,
        ]);

        $this->paymentGateway->shouldReceive('processPayment')
            ->with($expectedPaymentData, 63.25)
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Payment declined'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error al procesar el pago: Payment declined');

        $this->useCase->execute($userId, $paymentData, $shippingData);
    }
}