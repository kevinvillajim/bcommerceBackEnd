<?php

namespace Tests\Unit;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\ShoppingCartEntity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShoppingCartEntityTest extends TestCase
{
    #[Test]
    public function it_creates_cart_with_default_values()
    {
        $cart = new ShoppingCartEntity(1, 10);

        $this->assertEquals(1, $cart->getId());
        $this->assertEquals(10, $cart->getUserId());
        $this->assertEmpty($cart->getItems());
        $this->assertEquals(0, $cart->getTotal());
        $this->assertInstanceOf(\DateTime::class, $cart->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $cart->getUpdatedAt());
    }

    #[Test]
    public function it_adds_new_item()
    {
        $cart = new ShoppingCartEntity(1, 10);

        $item = new CartItemEntity(1, 1, 100, 2, 50, 100);
        $cart->addItem($item);

        $this->assertCount(1, $cart->getItems());
        $this->assertEquals(100, $cart->getTotal());
    }

    #[Test]
    public function it_updates_quantity_when_adding_existing_product()
    {
        $cart = new ShoppingCartEntity(1, 10);

        // Añadir item inicial
        $item1 = new CartItemEntity(1, 1, 100, 2, 50, 100);
        $cart->addItem($item1);

        // Añadir item con mismo producto
        $item2 = new CartItemEntity(2, 1, 100, 3, 50, 150);
        $cart->addItem($item2);

        // Verificar que sólo hay un item pero con cantidad actualizada
        $this->assertCount(1, $cart->getItems());
        $this->assertEquals(250, $cart->getTotal());
        $this->assertEquals(5, $cart->getItems()[0]->getQuantity());
    }

    #[Test]
    public function it_removes_item()
    {
        $cart = new ShoppingCartEntity(1, 10);

        // Añadir dos items
        $item1 = new CartItemEntity(1, 1, 100, 2, 50, 100);
        $item2 = new CartItemEntity(2, 1, 200, 1, 75, 75);

        $cart->addItem($item1);
        $cart->addItem($item2);

        $this->assertCount(2, $cart->getItems());
        $this->assertEquals(175, $cart->getTotal());

        // Eliminar un item
        $cart->removeItem(1);

        $this->assertCount(1, $cart->getItems());
        $this->assertEquals(75, $cart->getTotal());
    }

    #[Test]
    public function it_updates_item_quantity()
    {
        $cart = new ShoppingCartEntity(1, 10);

        // Añadir item
        $item = new CartItemEntity(1, 1, 100, 2, 50, 100);
        $cart->addItem($item);

        $this->assertEquals(100, $cart->getTotal());

        // Actualizar cantidad
        $cart->updateItemQuantity(1, 3);

        $this->assertEquals(150, $cart->getTotal());
        $this->assertEquals(3, $cart->getItems()[0]->getQuantity());
    }

    #[Test]
    public function it_empties_cart()
    {
        $cart = new ShoppingCartEntity(1, 10);

        // Añadir items
        $item1 = new CartItemEntity(1, 1, 100, 2, 50, 100);
        $item2 = new CartItemEntity(2, 1, 200, 1, 75, 75);

        $cart->addItem($item1);
        $cart->addItem($item2);

        $this->assertCount(2, $cart->getItems());
        $this->assertEquals(175, $cart->getTotal());

        // Vaciar carrito
        $cart->empty();

        $this->assertEmpty($cart->getItems());
        $this->assertEquals(0, $cart->getTotal());
    }
}
