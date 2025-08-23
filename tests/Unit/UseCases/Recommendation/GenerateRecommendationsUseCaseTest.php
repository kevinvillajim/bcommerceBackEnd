<?php

namespace Tests\Unit\UseCases\Recommendation;

use App\Domain\Interfaces\RecommendationEngineInterface;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateRecommendationsUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private $mockEngine;

    private $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear mock del motor de recomendaciones correctamente
        $this->mockEngine = Mockery::mock(RecommendationEngineInterface::class);

        // Crear caso de uso
        $this->useCase = new GenerateRecommendationsUseCase($this->mockEngine);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_executes_and_returns_recommendations()
    {
        $userId = 1;
        $limit = 5;

        // Datos de recomendación mock
        $mockRecommendations = [
            [
                'id' => 1,
                'name' => 'Producto 1',
                'price' => 100,
                'category_id' => 1,
                'rating' => 4.5,
                'recommendation_type' => 'personalized',
            ],
            [
                'id' => 2,
                'name' => 'Producto 2',
                'price' => 200,
                'category_id' => 1,
                'rating' => 4.0,
                'recommendation_type' => 'personalized',
            ],
        ];

        // Configurar expectativa para el mock correctamente
        $this->mockEngine->shouldReceive('generateRecommendations')
            ->once()
            ->with($userId, $limit)
            ->andReturn($mockRecommendations);

        // Ejecutar caso de uso
        $result = $this->useCase->execute($userId, $limit);

        // Verificar resultado
        $this->assertEquals($mockRecommendations, $result);
    }
}
