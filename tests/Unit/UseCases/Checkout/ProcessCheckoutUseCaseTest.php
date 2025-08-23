<?php

namespace Tests\Unit\UseCases\Checkout;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\OrderEntity;
use App\Domain\Entities\ProductEntity;
use App\Domain\Entities\ShoppingCartEntity;
use App\Domain\Interfaces\PaymentGatewayInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
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

    protected $paymentGateway;

    protected $createOrderUseCase;

    protected $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(ShoppingCartRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->paymentGateway = Mockery::mock(PaymentGatewayInterface::class);
        $this->createOrderUseCase = Mockery::mock(CreateOrderUseCase::class);

        $this->useCase = new ProcessCheckoutUseCase(
            $this->cartRepository,
            $this->orderRepository,
            $this->productRepository,
            $this->paymentGateway,
            $this->createOrderUseCase
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

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($cart);

        // Simular verificación de stock
        $product1 = Mockery::mock(ProductEntity::class);
        $product1->shouldReceive('getId')->andReturn(100);
        $product1->shouldReceive('getName')->andReturn('Producto 1');
        $product1->shouldReceive('getStock')->andReturn(10);

        $product2 = Mockery::mock(ProductEntity::class);
        $product2->shouldReceive('getId')->andReturn(200);
        $product2->shouldReceive('getName')->andReturn('Producto 2');
        $product2->shouldReceive('getStock')->andReturn(5);

        $this->productRepository->shouldReceive('findById')
            ->with(100)
            ->once()
            ->andReturn($product1);

        $this->productRepository->shouldReceive('findById')
            ->with(200)
            ->once()
            ->andReturn($product2);

        // Simular creación de orden
        $order = Mockery::mock(OrderEntity::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getTotal')->andReturn(175);
        $order->shouldReceive('getOrderNumber')->andReturn('ORD-12345');

        // Cambio: usar mock en lugar de clase concreta
        $this->createOrderUseCase->shouldReceive('execute')
            ->withAnyArgs()
            ->once()
            ->andReturn($order);

        // Simular procesamiento de pago
        $paymentResult = [
            'success' => true,
            'payment_id' => 'PAY-12345',
            'status' => 'completed',
            'message' => 'Payment processed successfully',
        ];

        $this->paymentGateway->shouldReceive('processPayment')
            ->with($paymentData, 175)
            ->once()
            ->andReturn($paymentResult);

        // Simular actualización de orden con info de pago
        $this->orderRepository->shouldReceive('updatePaymentInfo')
            ->with($orderId, Mockery::type('array'))
            ->once();

        // Simular actualización de stock
        $this->productRepository->shouldReceive('updateStock')
            ->with(100, 2, 'decrease')
            ->once();

        $this->productRepository->shouldReceive('updateStock')
            ->with(200, 1, 'decrease')
            ->once();

        // Simular vaciado del carrito
        $this->cartRepository->shouldReceive('clearCart')
            ->with($cartId)
            ->once();

        // Simular obtención de orden completa
        $completedOrder = Mockery::mock(OrderEntity::class);
        $completedOrder->shouldReceive('getId')->andReturn($orderId);
        $completedOrder->shouldReceive('getTotal')->andReturn(175);
        $completedOrder->shouldReceive('getOrderNumber')->andReturn('ORD-12345');

        $this->orderRepository->shouldReceive('findById')
            ->with($orderId)
            ->once()
            ->andReturn($completedOrder);

        // Ejecutar caso de uso
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->useCase->execute($userId, $paymentData, $shippingData);

        // Verificar resultado
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame($completedOrder, $result['order']);
        $this->assertSame($paymentResult, $result['payment']);
    }

    #[Test]
    public function it_throws_exception_if_cart_is_empty()
    {
        $userId = 1;

        // Datos de pago y envío
        $paymentData = ['method' => 'credit_card'];
        $shippingData = ['address' => 'Calle Falsa 123'];

        // Simular carrito vacío
        $emptyCart = new ShoppingCartEntity(5, $userId, [], 0);

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($emptyCart);

        // Configurar simulación de transacción
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Verificar que se lanza excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El carrito está vacío');

        $this->useCase->execute($userId, $paymentData, $shippingData);
    }

    #[Test]
    public function it_throws_exception_if_payment_fails()
    {
        $userId = 1;
        $cartId = 5;
        $orderId = 100;

        // Datos de pago y envío
        $paymentData = ['method' => 'credit_card'];
        $shippingData = ['address' => 'Calle Falsa 123'];

        // Simular item en el carrito
        $item = new CartItemEntity(1, $cartId, 100, 2, 50, 100);

        // Simular carrito
        $cart = new ShoppingCartEntity(
            $cartId,
            $userId,
            [$item],
            100
        );

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($cart);

        // Simular verificación de stock
        $product = Mockery::mock(ProductEntity::class);
        $product->shouldReceive('getId')->andReturn(100);
        $product->shouldReceive('getName')->andReturn('Producto 1');
        $product->shouldReceive('getStock')->andReturn(10);

        $this->productRepository->shouldReceive('findById')
            ->with(100)
            ->once()
            ->andReturn($product);

        // Simular creación de orden
        $order = Mockery::mock(OrderEntity::class);
        $order->shouldReceive('getId')->andReturn($orderId);
        $order->shouldReceive('getTotal')->andReturn(100);

        $this->createOrderUseCase->shouldReceive('execute')
            ->with(Mockery::any())
            ->once()
            ->andReturn($order);

        // Simular fallo en el procesamiento del pago
        $paymentResult = [
            'success' => false,
            'message' => 'Tarjeta rechazada',
        ];

        $this->paymentGateway->shouldReceive('processPayment')
            ->with($paymentData, 100)
            ->once()
            ->andReturn($paymentResult);

        // Configurar simulación de transacción
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Verificar que se lanza excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error al procesar el pago: Tarjeta rechazada');

        $this->useCase->execute($userId, $paymentData, $shippingData);
    }
}
