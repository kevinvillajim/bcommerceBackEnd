<?php

namespace Tests\Unit\Infrastructure\Repositories;

use App\Domain\Entities\ProductEntity;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected EloquentProductRepository $repository;

    protected User $user;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentProductRepository;
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
    }

    #[Test]
    public function it_can_create_a_product()
    {
        // Crear un ProductEntity para guardar
        $productEntity = new ProductEntity(
            $this->user->id,
            $this->category->id,
            'Test Product',
            'test-product',
            'Test Description',
            99.99,
            10,
            null,
            null,
            null,
            null,
            null,
            ['red', 'blue'],
            ['S', 'M', 'L'],
            ['electronics', 'gadget'],
            'SKU123',
            ['material' => 'plastic'],
            null,
            false,
            true,
            'active',
            0,
            0,
            0
        );

        $createdProduct = $this->repository->create($productEntity);

        // Verificar que se creó correctamente
        $this->assertInstanceOf(ProductEntity::class, $createdProduct);
        $this->assertNotNull($createdProduct->getId());
        $this->assertEquals('Test Product', $createdProduct->getName());

        // Verificar que se guardó en la base de datos
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'stock' => 10,
        ]);
    }

    #[Test]
    public function it_can_find_a_product_by_id()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'Find By ID Test',
            'slug' => 'find-by-id-test',
            'price' => 149.99,
            'stock' => 5,
        ]);

        // Buscar el producto usando el repositorio
        $foundProduct = $this->repository->findById($product->id);

        // Verificar que se encontró correctamente
        $this->assertInstanceOf(ProductEntity::class, $foundProduct);
        $this->assertEquals($product->id, $foundProduct->getId());
        $this->assertEquals('Find By ID Test', $foundProduct->getName());
        $this->assertEquals(149.99, $foundProduct->getPrice());
    }

    #[Test]
    public function it_returns_null_when_product_not_found()
    {
        // Intentar encontrar un producto inexistente
        $nonExistentProduct = $this->repository->findById(999999);

        // Debería devolver null
        $this->assertNull($nonExistentProduct);
    }

    #[Test]
    public function it_can_find_a_product_by_slug()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'Find By Slug Test',
            'slug' => 'find-by-slug-test',
            'price' => 129.99,
            'stock' => 15,
        ]);

        // Buscar el producto usando el repositorio
        $foundProduct = $this->repository->findBySlug('find-by-slug-test');

        // Verificar que se encontró correctamente
        $this->assertInstanceOf(ProductEntity::class, $foundProduct);
        $this->assertEquals($product->id, $foundProduct->getId());
        $this->assertEquals('Find By Slug Test', $foundProduct->getName());
    }

    #[Test]
    public function it_can_update_a_product()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'Original Product',
            'slug' => 'original-product',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Buscar el producto para actualizarlo
        $productEntity = $this->repository->findById($product->id);

        // Modificar el producto
        $productEntity->setName('Updated Product');
        $productEntity->setPrice(199.99);
        $productEntity->setStock(20);

        // Actualizar usando el repositorio
        $this->repository->update($productEntity);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'price' => 199.99,
            'stock' => 20,
        ]);
    }

    #[Test]
    public function it_dispatches_events_when_updating_product()
    {
        Event::fake();

        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'stock' => 10,
        ]);

        // Buscar el producto para actualizarlo
        $productEntity = $this->repository->findById($product->id);

        // Modificar el stock del producto
        $productEntity->setStock(5);

        // Actualizar usando el repositorio
        $this->repository->update($productEntity);

        // Verificar que se disparó el evento
        Event::assertDispatched(\App\Events\ProductStockUpdated::class);
    }

    #[Test]
    public function it_can_delete_a_product()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'name' => 'Delete Test Product',
            'slug' => 'delete-test-product',
        ]);

        // Verificar que existe
        $this->assertDatabaseHas('products', [
            'slug' => 'delete-test-product',
        ]);

        // Eliminar el producto
        $result = $this->repository->delete($product->id);

        // Verificar que el resultado es verdadero
        $this->assertTrue($result);

        // Verificar que se eliminó (en realidad se hace soft delete)
        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    #[Test]
    public function it_can_increment_view_count()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'view_count' => 5,
        ]);

        // Incrementar contador de vistas
        $result = $this->repository->incrementViewCount($product->id);

        // Verificar que el resultado es verdadero
        $this->assertTrue($result);

        // Verificar que se incrementó
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'view_count' => 6,
        ]);
    }

    #[Test]
    public function it_can_update_stock()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'stock' => 10,
        ]);

        // Actualizar el stock del producto (incrementar)
        $this->repository->updateStock($product->id, 5, 'increase');

        // Verificar que se actualizó
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 15,
        ]);

        // Actualizar el stock del producto (decrementar)
        $this->repository->updateStock($product->id, 3, 'decrease');

        // Verificar que se actualizó
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 12,
        ]);

        // Actualizar el stock del producto (reemplazar)
        $this->repository->updateStock($product->id, 20, 'replace');

        // Verificar que se actualizó
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 20,
        ]);
    }

    #[Test]
    public function it_can_find_products_by_category()
    {
        // Crear categorías
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        // Crear productos en cada categoría
        Product::factory()->count(3)->create([
            'category_id' => $categoryA->id,
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->count(2)->create([
            'category_id' => $categoryB->id,
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos por categoría
        $productsInCategoryA = $this->repository->findByCategory($categoryA->id, 10, 0);

        // Verificar que solo se encontraron productos de la categoría A
        $this->assertCount(3, $productsInCategoryA);
        foreach ($productsInCategoryA as $product) {
            $this->assertEquals($categoryA->id, $product->getCategoryId());
        }
    }

    #[Test]
    public function it_can_search_products()
    {
        // Crear productos con diferentes nombres
        Product::factory()->create([
            'name' => 'iPhone 13 Pro',
            'description' => 'Latest Apple smartphone',
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Samsung Galaxy S22',
            'description' => 'Android flagship phone',
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'iPhone Charger',
            'description' => 'Fast charging for Apple devices',
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos por término
        $searchResults = $this->repository->search('iPhone', [], 10, 0);

        // Verificar que se encontraron los productos correctos
        $this->assertCount(2, $searchResults);

        $productNames = array_map(function ($product) {
            return $product->getName();
        }, $searchResults);

        $this->assertContains('iPhone 13 Pro', $productNames);
        $this->assertContains('iPhone Charger', $productNames);
        $this->assertNotContains('Samsung Galaxy S22', $productNames);
    }

    #[Test]
    public function it_can_find_featured_products()
    {
        // Crear productos destacados
        Product::factory()->count(2)->create([
            'featured' => true,
            'published' => true,
            'status' => 'active',
        ]);

        // Crear productos no destacados
        Product::factory()->count(3)->create([
            'featured' => false,
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos destacados
        $featuredProducts = $this->repository->findFeatured(10, 0);

        // Verificar que solo se encontraron productos destacados
        $this->assertCount(2, $featuredProducts);
        foreach ($featuredProducts as $product) {
            $this->assertTrue($product->isFeatured());
        }
    }

    #[Test]
    public function it_can_find_products_by_tags()
    {
        // Crear productos con diferentes tags
        Product::factory()->create([
            'name' => 'Product with Tag A',
            'tags' => ['electronics', 'smartphone'],
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Product with Tag B',
            'tags' => ['clothing', 'tshirt'],
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Product with Both Tags',
            'tags' => ['electronics', 'tshirt'],
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos por tags (electrónicos)
        $electronicsProducts = $this->repository->findByTags(['electronics'], 10, 0);

        // Verificar que se encontraron los productos correctos
        $this->assertCount(2, $electronicsProducts);

        $productNames = array_map(function ($product) {
            return $product->getName();
        }, $electronicsProducts);

        $this->assertContains('Product with Tag A', $productNames);
        $this->assertContains('Product with Both Tags', $productNames);
        $this->assertNotContains('Product with Tag B', $productNames);
    }

    #[Test]
    public function it_can_find_popular_products()
    {
        // Crear productos con diferentes niveles de popularidad
        Product::factory()->create([
            'name' => 'Most Popular Product',
            'view_count' => 100,
            'sales_count' => 50,
            'rating' => 4.8,
            'published' => true,
            'status' => 'active',
            'stock' => 10,
        ]);

        Product::factory()->create([
            'name' => 'Less Popular Product',
            'view_count' => 50,
            'sales_count' => 20,
            'rating' => 3.5,
            'published' => true,
            'status' => 'active',
            'stock' => 10,
        ]);

        Product::factory()->create([
            'name' => 'Unpopular Product',
            'view_count' => 10,
            'sales_count' => 5,
            'rating' => 2.0,
            'published' => true,
            'status' => 'active',
            'stock' => 10,
        ]);

        // Buscar productos populares
        $popularProducts = $this->repository->findPopularProducts(2);

        // Verificar que se encontraron los productos más populares
        $this->assertCount(2, $popularProducts);

        $productNames = array_map(function ($product) {
            return $product->getName();
        }, $popularProducts);

        $this->assertContains('Most Popular Product', $productNames);
        $this->assertContains('Less Popular Product', $productNames);
        $this->assertNotContains('Unpopular Product', $productNames);
    }
}
