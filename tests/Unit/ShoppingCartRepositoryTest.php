<?php

namespace Tests\Unit;

use App\Domain\Entities\CartItemEntity;
use App\Domain\Entities\ShoppingCartEntity;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShoppingCartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected ShoppingCartRepositoryInterface $repository;

    protected User $user;

    protected Product $product;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Primero creamos una categoría
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Test Description',
        ]);

        $this->repository = app(ShoppingCartRepositoryInterface::class);
        $this->user = User::factory()->create();

        // Crear un usuario vendedor para el producto
        $seller = User::factory()->create();

        // Creamos el producto con todos los campos requeridos
        $this->product = Product::create([
            'name' => 'Test Product',
            'description' => 'Test Description',
            'price' => 100.00,
            'stock' => 10,
            'category_id' => $this->category->id,
            'slug' => 'test-product',
            'user_id' => $seller->id,  // Campo obligatorio que faltaba
            'status' => 'active',
            'published' => true,
        ]);
    }

    #[Test]
    public function it_creates_a_shopping_cart()
    {
        $cartEntity = new ShoppingCartEntity(0, $this->user->id, [], 0);
        $savedCart = $this->repository->save($cartEntity);

        $this->assertNotNull($savedCart);
        $this->assertInstanceOf(ShoppingCartEntity::class, $savedCart);
        $this->assertGreaterThan(0, $savedCart->getId());
        $this->assertEquals($this->user->id, $savedCart->getUserId());
        $this->assertEquals(0, $savedCart->getTotal());
        $this->assertEmpty($savedCart->getItems());

        // Verificar que se creó en la base de datos
        $this->assertDatabaseHas('shopping_carts', [
            'id' => $savedCart->getId(),
            'user_id' => $this->user->id,
            'total' => 0,
        ]);
    }

    #[Test]
    public function it_adds_item_to_cart()
    {
        // Crear un carrito
        $cart = ShoppingCart::create([
            'user_id' => $this->user->id,
            'total' => 0,
        ]);

        // Crear un item y añadirlo al carrito
        $itemEntity = new CartItemEntity(
            0,
            $cart->id,
            $this->product->id,
            2,
            $this->product->price,
            $this->product->price * 2
        );

        $addedItem = $this->repository->addItem($cart->id, $itemEntity);

        // Verificar el item añadido
        $this->assertNotNull($addedItem);
        $this->assertInstanceOf(CartItemEntity::class, $addedItem);
        $this->assertGreaterThan(0, $addedItem->getId());
        $this->assertEquals($cart->id, $addedItem->getCartId());
        $this->assertEquals($this->product->id, $addedItem->getProductId());
        $this->assertEquals(2, $addedItem->getQuantity());
        $this->assertEquals($this->product->price, $addedItem->getPrice());
        $this->assertEquals($this->product->price * 2, $addedItem->getSubtotal());

        // Verificar que el total del carrito se ha actualizado
        $updatedCart = $this->repository->findByUserId($this->user->id);
        $this->assertEquals($this->product->price * 2, $updatedCart->getTotal());

        // Verificar en la base de datos
        $this->assertDatabaseHas('cart_items', [
            'id' => $addedItem->getId(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
            'subtotal' => $this->product->price * 2,
        ]);

        $this->assertDatabaseHas('shopping_carts', [
            'id' => $cart->id,
            'total' => $this->product->price * 2,
        ]);
    }

    #[Test]
    public function it_removes_item_from_cart()
    {
        // Crear un carrito
        $cart = ShoppingCart::create([
            'user_id' => $this->user->id,
            'total' => 0,
        ]);

        // Crear un item en el carrito
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
            'subtotal' => $this->product->price * 2,
        ]);

        // Actualizar el total del carrito
        $cart->update(['total' => $this->product->price * 2]);

        // Eliminar el item
        $removed = $this->repository->removeItem($cart->id, $cartItem->id);

        // Verificar que se ha eliminado
        $this->assertTrue($removed);
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);

        // Verificar que el total del carrito se ha actualizado
        $updatedCart = $this->repository->findByUserId($this->user->id);
        $this->assertEquals(0, $updatedCart->getTotal());
        $this->assertEmpty($updatedCart->getItems());
    }

    #[Test]
    public function it_updates_item_quantity()
    {
        // Crear un carrito
        $cart = ShoppingCart::create([
            'user_id' => $this->user->id,
            'total' => 0,
        ]);

        // Crear un item en el carrito
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
            'subtotal' => $this->product->price * 2,
        ]);

        // Actualizar el total del carrito
        $cart->update(['total' => $this->product->price * 2]);

        // Actualizar cantidad
        $updated = $this->repository->updateItemQuantity($cart->id, $cartItem->id, 3);

        // Verificar que se ha actualizado
        $this->assertTrue($updated);
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 3,
            'subtotal' => $this->product->price * 3,
        ]);

        // Verificar que el total del carrito se ha actualizado
        $updatedCart = $this->repository->findByUserId($this->user->id);
        $this->assertEquals($this->product->price * 3, $updatedCart->getTotal());
    }

    #[Test]
    public function it_finds_cart_by_user_id()
    {
        // Crear un carrito
        $cart = ShoppingCart::create([
            'user_id' => $this->user->id,
            'total' => 0,
        ]);

        // Crear un item en el carrito
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
            'subtotal' => $this->product->price * 2,
        ]);

        // Actualizar el total del carrito
        $cart->update(['total' => $this->product->price * 2]);

        // Buscar el carrito por ID de usuario
        $foundCart = $this->repository->findByUserId($this->user->id);

        // Verificar que el carrito encontrado es correcto
        $this->assertNotNull($foundCart);
        $this->assertInstanceOf(ShoppingCartEntity::class, $foundCart);
        $this->assertEquals($cart->id, $foundCart->getId());
        $this->assertEquals($this->user->id, $foundCart->getUserId());
        $this->assertEquals($this->product->price * 2, $foundCart->getTotal());
        $this->assertCount(1, $foundCart->getItems());
    }

    #[Test]
    public function it_clears_cart()
    {
        // Crear un carrito
        $cart = ShoppingCart::create([
            'user_id' => $this->user->id,
            'total' => 0,
        ]);

        // Crear varios items para este carrito
        for ($i = 0; $i < 3; $i++) {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'price' => $this->product->price,
                'subtotal' => $this->product->price,
            ]);
        }

        // Actualizar el total del carrito
        $cart->update(['total' => $this->product->price * 3]);

        // Verificar que hay 3 items en el carrito
        $this->assertEquals(3, CartItem::where('cart_id', $cart->id)->count());

        // Limpiar el carrito
        $cleared = $this->repository->clearCart($cart->id);

        // Verificar que se ha limpiado
        $this->assertTrue($cleared);
        $this->assertEquals(0, CartItem::where('cart_id', $cart->id)->count());

        // Verificar que el total del carrito se ha actualizado
        $updatedCart = $this->repository->findByUserId($this->user->id);
        $this->assertEquals(0, $updatedCart->getTotal());
        $this->assertEmpty($updatedCart->getItems());
    }
}
