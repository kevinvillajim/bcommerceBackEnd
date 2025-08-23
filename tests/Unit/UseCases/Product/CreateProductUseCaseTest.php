<?php

namespace Tests\Unit\UseCases\Product;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Services\FileUploadService;
use App\Models\Category;
use App\Models\User;
use App\UseCases\Product\CreateProductUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateProductUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private $productRepository;

    private $fileUploadService;

    private $useCase;

    private User $seller;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar almacenamiento falso para testing
        Storage::fake('public');

        // Crear vendedor y categoría para pruebas
        $this->seller = User::factory()->create();
        $this->category = Category::factory()->create();

        // Mockear el repositorio y el servicio de carga de archivos correctamente
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->fileUploadService = Mockery::mock(FileUploadService::class);

        // Crear el caso de uso
        $this->useCase = new CreateProductUseCase(
            $this->productRepository,
            $this->fileUploadService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_a_product_with_basic_data()
    {
        // Preparar datos de entrada
        $data = [
            'user_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'This is a test product',
            'price' => 99.99,
            'stock' => 10,
        ];

        // Configurar expectativas del mock
        $this->productRepository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (ProductEntity $product) {
                // Simular ID asignado por la base de datos
                $product->setId(1);

                return $product;
            });

        // Ejecutar caso de uso
        $result = $this->useCase->execute($data);

        // Verificar resultados
        $this->assertInstanceOf(ProductEntity::class, $result);
        $this->assertEquals(1, $result->getId());
        $this->assertEquals($this->seller->id, $result->getUserId());
        $this->assertEquals($this->category->id, $result->getCategoryId());
        $this->assertEquals('Test Product', $result->getName());
        $this->assertEquals('test-product', $result->getSlug());
        $this->assertEquals('This is a test product', $result->getDescription());
        $this->assertEquals(99.99, $result->getPrice());
        $this->assertEquals(10, $result->getStock());
    }
}
