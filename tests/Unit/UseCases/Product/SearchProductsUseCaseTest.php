<?php

namespace Tests\Unit\UseCases\Product;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Product\SearchProductsUseCase;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchProductsUseCaseTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;

    private TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    private SearchProductsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mockear dependencias
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->trackUserInteractionsUseCase = Mockery::mock(TrackUserInteractionsUseCase::class);

        // Crear caso de uso
        $this->useCase = new SearchProductsUseCase(
            $this->productRepository,
            $this->trackUserInteractionsUseCase
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_searches_products_with_term()
    {
        // Crear productos de prueba
        $products = [
            $this->createMockProduct(1, 'Test Product 1', 'test-product-1', 99.99),
            $this->createMockProduct(2, 'Test Product 2', 'test-product-2', 149.99),
        ];

        // Configurar expectativas del mock del repositorio
        $this->productRepository->shouldReceive('search')
            ->once()
            ->with('test', [], 10, 0)
            ->andReturn($products);

        $this->productRepository->shouldReceive('count')
            ->once()
            ->with(['search' => 'test'])
            ->andReturn(2);

        // Configurar expectativas del mock para trackear interacciones
        $this->trackUserInteractionsUseCase->shouldReceive('execute')
            ->once()
            ->with(1, 'search', 0, Mockery::on(function ($metadata) {
                return $metadata['term'] === 'test' &&
                    $metadata['results_count'] === 2 &&
                    $metadata['total_count'] === 2;
            }));

        // Ejecutar caso de uso
        $result = $this->useCase->execute('test', [], 10, 0, 1);

        // Verificar resultados
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals('test', $result['meta']['term']);
        $this->assertEquals(2, $result['meta']['total']);
    }

    #[Test]
    public function it_searches_products_with_filters()
    {
        // Crear productos de prueba
        $products = [
            $this->createMockProduct(3, 'Filtered Product', 'filtered-product', 199.99),
        ];

        // Definir filtros
        $filters = [
            'price_min' => 150,
            'price_max' => 250,
            'category_id' => 2,
        ];

        // Configurar expectativas del mock del repositorio
        $this->productRepository->shouldReceive('search')
            ->once()
            ->with('', $filters, 10, 0)
            ->andReturn($products);

        $this->productRepository->shouldReceive('count')
            ->once()
            ->with(array_merge($filters, ['search' => '']))
            ->andReturn(1);

        // Ejecutar caso de uso sin usuario (no se debe trackear)
        $result = $this->useCase->execute('', $filters, 10, 0);

        // Verificar resultados
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals($filters, $result['meta']['filters']);
    }

    #[Test]
    public function it_searches_products_by_category()
    {
        // Crear productos de prueba
        $products = [
            $this->createMockProduct(4, 'Category Product 1', 'category-product-1', 49.99),
            $this->createMockProduct(5, 'Category Product 2', 'category-product-2', 59.99),
        ];

        // ID de categoría
        $categoryId = 3;

        // Configurar expectativas del mock del repositorio
        $this->productRepository->shouldReceive('findByCategory')
            ->once()
            ->with($categoryId, 10, 0)
            ->andReturn($products);

        $this->productRepository->shouldReceive('count')
            ->once()
            ->with(['category_id' => $categoryId])
            ->andReturn(2);

        // Configurar expectativas del mock para trackear interacciones
        $this->trackUserInteractionsUseCase->shouldReceive('execute')
            ->once()
            ->with(2, 'browse_category', $categoryId, Mockery::on(function ($metadata) {
                return $metadata['results_count'] === 2 &&
                    $metadata['total_count'] === 2;
            }));

        // Ejecutar caso de uso
        $result = $this->useCase->executeByCategory($categoryId, 10, 0, 2);

        // Verificar resultados
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals($categoryId, $result['meta']['category_id']);
    }

    #[Test]
    public function it_searches_products_by_tags()
    {
        // Crear productos de prueba
        $products = [
            $this->createMockProduct(6, 'Tagged Product', 'tagged-product', 79.99),
        ];

        // Tags para buscar
        $tags = ['special', 'featured'];

        // Configurar expectativas del mock del repositorio
        $this->productRepository->shouldReceive('findByTags')
            ->once()
            ->with($tags, 10, 0)
            ->andReturn($products);

        // Configurar expectativas del mock para trackear interacciones
        $this->trackUserInteractionsUseCase->shouldReceive('execute')
            ->once()
            ->with(3, 'search_tags', 0, Mockery::on(function ($metadata) use ($tags) {
                return $metadata['tags'] === $tags &&
                    $metadata['results_count'] === 1;
            }));

        // Ejecutar caso de uso
        $result = $this->useCase->executeByTags($tags, 10, 0, 3);

        // Verificar resultados
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals($tags, $result['meta']['tags']);
    }

    /**
     * Helper para crear un ProductEntity mock
     */
    private function createMockProduct(int $id, string $name, string $slug, float $price): ProductEntity
    {
        $product = Mockery::mock(ProductEntity::class);

        $product->shouldReceive('getId')->andReturn($id);
        $product->shouldReceive('getName')->andReturn($name);
        $product->shouldReceive('getSlug')->andReturn($slug);
        $product->shouldReceive('getPrice')->andReturn($price);
        $product->shouldReceive('toArray')->andReturn([
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
        ]);

        return $product;
    }
}
