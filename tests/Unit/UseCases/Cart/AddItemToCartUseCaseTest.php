<?php

namespace Tests\Unit\UseCases\Cart;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\ProductEntity;
use App\Domain\Entities\ShoppingCartEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\UseCases\Cart\AddItemToCartUseCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddItemToCartUseCaseTest extends TestCase
{
    protected ShoppingCartRepositoryInterface $cartRepository;

    protected ProductRepositoryInterface $productRepository;

    protected AddItemToCartUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(ShoppingCartRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->useCase = new AddItemToCartUseCase(
            $this->cartRepository,
            $this->productRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_adds_product_to_existing_cart()
    {
        $userId = 1;
        $productId = 100;
        $quantity = 2;
        $price = 50.0;

        // Crear un mock de ProductEntity en lugar de instanciarlo directamente
        $product = Mockery::mock(ProductEntity::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getPrice')->andReturn($price);
        $product->shouldReceive('getStock')->andReturn(10);

        $this->productRepository->shouldReceive('findById')
            ->with($productId)
            ->once()
            ->andReturn($product);

        // Simular carrito existente
        $cartId = 5;
        $cart = new ShoppingCartEntity(
            $cartId,
            $userId,
            [],
            0
        );

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($cart);

        // Simular item añadido
        $addedItem = new CartItemEntity(
            10, // ID asignado
            $cartId,
            $productId,
            $quantity,
            $price,
            $price * $quantity
        );

        $this->cartRepository->shouldReceive('addItem')
            ->with($cartId, Mockery::type(CartItemEntity::class))
            ->once()
            ->andReturn($addedItem);

        // Simular carrito actualizado
        $updatedCart = new ShoppingCartEntity(
            $cartId,
            $userId,
            [$addedItem],
            $price * $quantity
        );

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($updatedCart);

        // Ejecutar caso de uso
        $result = $this->useCase->execute($userId, $productId, $quantity);

        // Verificar resultado
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cart', $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertSame($updatedCart, $result['cart']);
        $this->assertSame($addedItem, $result['item']);
    }

    #[Test]
    public function it_creates_cart_if_not_exists()
    {
        $userId = 1;
        $productId = 100;
        $quantity = 2;
        $price = 50.0;

        // Crear un mock de ProductEntity
        $product = Mockery::mock(ProductEntity::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getPrice')->andReturn($price);
        $product->shouldReceive('getStock')->andReturn(10);

        $this->productRepository->shouldReceive('findById')
            ->with($productId)
            ->once()
            ->andReturn($product);

        // Simular que no hay carrito existente
        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn(null);

        // Simular creación de carrito
        $cartId = 5;
        $createdCart = new ShoppingCartEntity(
            $cartId,
            $userId,
            [],
            0
        );

        $this->cartRepository->shouldReceive('save')
            ->with(Mockery::type(ShoppingCartEntity::class))
            ->once()
            ->andReturn($createdCart);

        // Simular item añadido
        $addedItem = new CartItemEntity(
            10, // ID asignado
            $cartId,
            $productId,
            $quantity,
            $price,
            $price * $quantity
        );

        $this->cartRepository->shouldReceive('addItem')
            ->with($cartId, Mockery::type(CartItemEntity::class))
            ->once()
            ->andReturn($addedItem);

        // Simular carrito actualizado
        $updatedCart = new ShoppingCartEntity(
            $cartId,
            $userId,
            [$addedItem],
            $price * $quantity
        );

        $this->cartRepository->shouldReceive('findByUserId')
            ->with($userId)
            ->once()
            ->andReturn($updatedCart);

        // Ejecutar caso de uso
        $result = $this->useCase->execute($userId, $productId, $quantity);

        // Verificar resultado
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cart', $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertSame($updatedCart, $result['cart']);
        $this->assertSame($addedItem, $result['item']);
    }

    #[Test]
    public function it_throws_exception_if_product_not_found()
    {
        $userId = 1;
        $productId = 100;
        $quantity = 2;

        // Simular producto no encontrado
        $this->productRepository->shouldReceive('findById')
            ->with($productId)
            ->once()
            ->andReturn(null);

        // Verificar que se lanza excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Producto no encontrado');

        $this->useCase->execute($userId, $productId, $quantity);
    }

    #[Test]
    public function it_throws_exception_if_insufficient_stock()
    {
        $userId = 1;
        $productId = 100;
        $quantity = 20; // Mayor que el stock disponible

        // Crear un mock de ProductEntity con stock insuficiente
        $product = Mockery::mock(ProductEntity::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getPrice')->andReturn(50.0);
        $product->shouldReceive('getStock')->andReturn(10); // Stock menor que la cantidad solicitada

        $this->productRepository->shouldReceive('findById')
            ->with($productId)
            ->once()
            ->andReturn($product);

        // Verificar que se lanza excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $this->useCase->execute($userId, $productId, $quantity);
    }
}
