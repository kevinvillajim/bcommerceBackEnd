<?php

namespace Tests\Unit\UseCases\Feedback;

use App\Domain\Entities\DiscountCodeEntity;
use App\Domain\Entities\FeedbackEntity;
use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\DiscountCodeRepositoryInterface;
use App\Domain\Repositories\FeedbackRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Feedback\ApplyDiscountCodeUseCase;
use App\UseCases\Feedback\GenerateDiscountCodeUseCase;
use Mockery;
use Tests\TestCase;

class DiscountCodeUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_discount_code_use_case()
    {
        // Mock de los repositorios
        $feedbackRepository = Mockery::mock(FeedbackRepositoryInterface::class);
        $discountCodeRepository = Mockery::mock(DiscountCodeRepositoryInterface::class);

        // Configurar comportamiento del mock
        $feedback = new FeedbackEntity(
            1, // userId
            'Test Feedback',
            'Description for testing',
            null, // sellerId
            'improvement',
            'approved', // status
            null, // adminNotes
            1, // reviewedBy (adminId)
            '2025-03-24 10:00:00', // reviewedAt
            1 // feedbackId
        );

        $feedbackRepository->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($feedback);

        $discountCodeRepository->shouldReceive('findByFeedbackId')
            ->with(1)
            ->once()
            ->andReturn(null);

        $discountCodeRepository->shouldReceive('generateUniqueCode')
            ->once()
            ->andReturn('ABC123');

        $discountCode = new DiscountCodeEntity(
            1, // feedbackId
            'ABC123', // code
            5.00, // discountPercentage
            false, // isUsed
            null, // usedBy
            null, // usedAt
            null, // usedOnProductId
            '2025-04-24 10:00:00', // expiresAt
            1 // id
        );

        $discountCodeRepository->shouldReceive('create')
            ->once()
            ->andReturn($discountCode);

        // Crear el caso de uso con los mocks
        $generateDiscountCodeUseCase = new GenerateDiscountCodeUseCase(
            $discountCodeRepository,
            $feedbackRepository
        );

        // Ejecutar el caso de uso
        $result = $generateDiscountCodeUseCase->execute(1, 30);

        // Verificar resultado
        $this->assertInstanceOf(DiscountCodeEntity::class, $result);
        $this->assertEquals('ABC123', $result->getCode());
        $this->assertEquals(5.00, $result->getDiscountPercentage());
        $this->assertEquals(1, $result->getFeedbackId());
    }

    public function test_apply_discount_code_use_case()
    {
        // Mock de los repositorios
        $discountCodeRepository = Mockery::mock(DiscountCodeRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        // Configurar comportamiento del mock
        $discountCode = new DiscountCodeEntity(
            1, // feedbackId
            'ABC123', // code
            5.00, // discountPercentage
            false, // isUsed
            null, // usedBy
            null, // usedAt
            null, // usedOnProductId
            '2025-04-24 10:00:00', // expiresAt
            1 // id
        );

        $discountCodeRepository->shouldReceive('findByCode')
            ->with('ABC123')
            ->once()
            ->andReturn($discountCode);

        $product = new ProductEntity(
            1, // userId
            1, // categoryId
            'Test Product',
            'test-product',
            'Product description',
            100.00, // price
            10, // stock
            null, // weight
            null, // width
            null, // height
            null, // depth
            null, // dimensions
            null, // colors
            null, // sizes
            null, // tags
            null, // sku
            null, // attributes
            null, // images
            false, // featured
            true, // published
            'active', // status
            0, // viewCount
            0, // salesCount
            0, // discountPercentage
            1 // id
        );

        $productRepository->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($product);

        $discountCodeRepository->shouldReceive('update')
            ->once()
            ->andReturn($discountCode);

        // Crear el caso de uso con los mocks
        $applyDiscountCodeUseCase = new ApplyDiscountCodeUseCase(
            $discountCodeRepository,
            $productRepository
        );

        // Ejecutar el caso de uso
        $result = $applyDiscountCodeUseCase->execute('ABC123', 1, 1);

        // Verificar resultado
        $this->assertTrue($result['success']);
        $this->assertEquals(5.00, $result['discount_percentage']);
        $this->assertEquals(5.00, $result['discount_amount']);
        $this->assertEquals(100.00, $result['original_price']);
        $this->assertEquals(95.00, $result['final_price']);
    }

    public function test_invalid_discount_code_returns_error()
    {
        // Mock de los repositorios
        $discountCodeRepository = Mockery::mock(DiscountCodeRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        // Configurar comportamiento del mock
        $discountCodeRepository->shouldReceive('findByCode')
            ->with('INVALID')
            ->once()
            ->andReturn(null);

        // Crear el caso de uso con los mocks
        $applyDiscountCodeUseCase = new ApplyDiscountCodeUseCase(
            $discountCodeRepository,
            $productRepository
        );

        // Ejecutar el caso de uso
        $result = $applyDiscountCodeUseCase->execute('INVALID', 1, 1);

        // Verificar resultado
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid discount code', $result['message']);
    }

    public function test_expired_discount_code_returns_error()
    {
        // Mock de los repositorios
        $discountCodeRepository = Mockery::mock(DiscountCodeRepositoryInterface::class);
        $productRepository = Mockery::mock(ProductRepositoryInterface::class);

        // Configurar comportamiento del mock - código expirado
        $discountCode = new DiscountCodeEntity(
            1, // feedbackId
            'EXPIRED', // code
            5.00, // discountPercentage
            false, // isUsed
            null, // usedBy
            null, // usedAt
            null, // usedOnProductId
            '2025-01-01 00:00:00', // expiresAt (fecha pasada)
            1 // id
        );

        $discountCodeRepository->shouldReceive('findByCode')
            ->with('EXPIRED')
            ->once()
            ->andReturn($discountCode);

        // Crear el caso de uso con los mocks
        $applyDiscountCodeUseCase = new ApplyDiscountCodeUseCase(
            $discountCodeRepository,
            $productRepository
        );

        // Ejecutar el caso de uso
        $result = $applyDiscountCodeUseCase->execute('EXPIRED', 1, 1);

        // Verificar resultado
        $this->assertFalse($result['success']);
        $this->assertEquals('This discount code has expired', $result['message']);
    }
}
