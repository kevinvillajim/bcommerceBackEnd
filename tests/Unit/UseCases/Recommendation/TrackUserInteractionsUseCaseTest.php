<?php

namespace Tests\Unit\UseCases\Recommendation;

use App\Domain\Interfaces\RecommendationEngineInterface;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackUserInteractionsUseCaseTest extends TestCase
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
        $this->useCase = new TrackUserInteractionsUseCase($this->mockEngine);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_tracks_user_interaction()
    {
        $userId = 1;
        $interactionType = 'view_product';
        $itemId = 5;
        $metadata = ['view_time' => 60];

        // Configurar expectativa para el mock correctamente
        $this->mockEngine->shouldReceive('trackUserInteraction')
            ->once()
            ->with($userId, $interactionType, $itemId, $metadata);

        // Ejecutar caso de uso
        $this->useCase->execute($userId, $interactionType, $itemId, $metadata);

        // La verificación se realiza implícitamente por Mockery
    }
}
