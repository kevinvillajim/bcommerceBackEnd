<?php

namespace Tests\Feature;

use App\Domain\Entities\ProductEntity;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentProductRepository $repository;

    private User $seller;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear el repositorio
        $this->repository = new EloquentProductRepository;

        // Crear usuario vendedor y categoría
        $this->seller = User::factory()->create();
        $this->category = Category::factory()->create();
    }

    #[Test]
    public function it_creates_a_product()
    {
        // Crear una entidad de producto
        $product = new ProductEntity(
            $this->seller->id,
            $this->category->id,
            'Smartphone XYZ',
            'smartphone-xyz',
            'Un smartphone de última generación',
            499.99,
            10,
            0.5,
            15.0,
            7.0,
            0.8,
            '15x7x0.8',
            ['red', 'blue', 'black'],
            ['64GB', '128GB'],
            ['smartphone', 'tech', 'mobile'],
            'SM-XYZ-123',
            ['display' => 'OLED', 'ram' => '8GB'],
            [['original' => 'products/image1.jpg']],
            true,
            true,
            'active',
            0,
            0,
            5.0
        );

        // Guardar el producto
        $savedProduct = $this->repository->create($product);

        // Verificar que se guardó correctamente
        $this->assertNotNull($savedProduct->getId());
        $this->assertEquals('Smartphone XYZ', $savedProduct->getName());
        $this->assertEquals('smartphone-xyz', $savedProduct->getSlug());
        $this->assertEquals(499.99, $savedProduct->getPrice());
        $this->assertEquals(10, $savedProduct->getStock());
        $this->assertEquals(['red', 'blue', 'black'], $savedProduct->getColors());
        $this->assertEquals(['smartphone', 'tech', 'mobile'], $savedProduct->getTags());

        // Verificar en la base de datos
        $this->assertDatabaseHas('products', [
            'id' => $savedProduct->getId(),
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Smartphone XYZ',
            'slug' => 'smartphone-xyz',
            'price' => 499.99,
            'stock' => 10,
            'featured' => true,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_finds_product_by_id()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'Test Description',
            'price' => 99.99,
            'stock' => 5,
            'tags' => ['test', 'product'],
        ]);

        // Buscar el producto por ID
        $foundProduct = $this->repository->findById($product->id);

        // Verificar que se encontró correctamente
        $this->assertNotNull($foundProduct);
        $this->assertEquals($product->id, $foundProduct->getId());
        $this->assertEquals('Test Product', $foundProduct->getName());
        $this->assertEquals('test-product', $foundProduct->getSlug());
        $this->assertEquals(99.99, $foundProduct->getPrice());
        $this->assertEquals(['test', 'product'], $foundProduct->getTags());
    }

    #[Test]
    public function it_finds_product_by_slug()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-slug',
            'description' => 'Test Description',
            'price' => 99.99,
            'stock' => 5,
        ]);

        // Buscar el producto por slug
        $foundProduct = $this->repository->findBySlug('test-product-slug');

        // Verificar que se encontró correctamente
        $this->assertNotNull($foundProduct);
        $this->assertEquals($product->id, $foundProduct->getId());
        $this->assertEquals('test-product-slug', $foundProduct->getSlug());
    }

    #[Test]
    public function it_updates_a_product()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Original Name',
            'description' => 'Original Description',
            'price' => 100.00,
            'stock' => 10,
        ]);

        // Obtener el producto y modificarlo
        $productEntity = $this->repository->findById($product->id);
        $productEntity->setName('Updated Name');
        $productEntity->setDescription('Updated Description');
        $productEntity->setPrice(150.00);
        $productEntity->setStock(15);
        $productEntity->setTags(['updated', 'tags']);

        // Guardar las actualizaciones
        $updatedProduct = $this->repository->update($productEntity);

        // Verificar que se actualizó correctamente
        $this->assertEquals('Updated Name', $updatedProduct->getName());
        $this->assertEquals('Updated Description', $updatedProduct->getDescription());
        $this->assertEquals(150.00, $updatedProduct->getPrice());
        $this->assertEquals(15, $updatedProduct->getStock());
        $this->assertEquals(['updated', 'tags'], $updatedProduct->getTags());

        // Verificar en la base de datos
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'price' => 150.00,
            'stock' => 15,
        ]);
    }

    #[Test]
    public function it_deletes_a_product()
    {
        // Crear un producto en la base de datos
        $product = Product::factory()->create([
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
        ]);

        // Eliminar el producto
        $result = $this->repository->delete($product->id);

        // Verificar que se eliminó correctamente
        $this->assertTrue($result);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function it_finds_products_by_category()
    {
        // Crear una categoría
        $category = Category::factory()->create();

        // Crear varios productos en esa categoría
        Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'published' => true,
            'status' => 'active',
        ]);

        // Crear productos en otra categoría
        Product::factory()->count(2)->create([
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos por categoría
        $products = $this->repository->findByCategory($category->id, 10, 0);

        // Verificar que se encontraron los productos correctos
        $this->assertCount(3, $products);
        $this->assertEquals($category->id, $products[0]->getCategoryId());
    }

    #[Test]
    public function it_searches_products()
    {
        // Crear productos con términos de búsqueda específicos
        Product::factory()->create([
            'name' => 'Test Search Product',
            'description' => 'This is a test product for search',
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Another Product',
            'description' => 'This has search term in description',
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'name' => 'Unrelated Product',
            'description' => 'This does not have the term',
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos con el término "search"
        $searchResults = $this->repository->search('search');

        // Verificar que se encontraron los productos correctos
        $this->assertCount(2, $searchResults);
    }

    #[Test]
    public function it_finds_products_by_tags()
    {
        // Crear productos con tags específicos
        Product::factory()->create([
            'tags' => ['electronics', 'smartphone'],
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'tags' => ['electronics', 'laptop'],
            'published' => true,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'tags' => ['clothing'],
            'published' => true,
            'status' => 'active',
        ]);

        // Buscar productos con el tag "electronics"
        $products = $this->repository->findByTags(['electronics']);

        // Verificar que se encontraron los productos correctos
        $this->assertCount(2, $products);

        // Buscar productos con el tag "smartphone"
        $smartphones = $this->repository->findByTags(['smartphone']);
        $this->assertCount(1, $smartphones);

        // Buscar productos con múltiples tags
        $specificProducts = $this->repository->findByTags(['electronics', 'laptop']);
        $this->assertCount(1, $specificProducts);
    }

    #[Test]
    public function it_increments_view_count()
    {
        // Crear un producto
        $product = Product::factory()->create([
            'view_count' => 0,
        ]);

        // Incrementar contadores de vista
        $this->repository->incrementViewCount($product->id);
        $this->repository->incrementViewCount($product->id);
        $this->repository->incrementViewCount($product->id);

        // Verificar en la base de datos
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'view_count' => 3,
        ]);

        // Verificar a través del repositorio
        $updatedProduct = $this->repository->findById($product->id);
        $this->assertEquals(3, $updatedProduct->getViewCount());
    }

    #[Test]
    public function it_updates_stock()
    {
        // Crear un producto
        $product = Product::factory()->create([
            'stock' => 10,
        ]);

        // Actualizar el stock
        $result = $this->repository->updateStock($product->id, 5);

        // Verificar que se actualizó correctamente
        $this->assertTrue($result);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 5,
        ]);

        // Verificar a través del repositorio
        $updatedProduct = $this->repository->findById($product->id);
        $this->assertEquals(5, $updatedProduct->getStock());
    }
}
