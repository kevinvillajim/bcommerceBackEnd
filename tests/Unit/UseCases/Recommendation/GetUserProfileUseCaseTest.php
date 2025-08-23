<?php

namespace Tests\Unit\UseCases\Recommendation;

use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Domain\ValueObjects\UserProfile;
use App\UseCases\Recommendation\GetUserProfileUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetUserProfileUseCaseTest extends TestCase
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
        $this->useCase = new GetUserProfileUseCase($this->mockEngine);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_executes_and_formats_user_profile()
    {
        $userId = 1;

        // Crear un perfil de usuario mock
        $profile = new UserProfile(
            ['smartphones' => 5, 'laptops' => 3],
            [['term' => 'iPhone', 'timestamp' => time()]],
            [['product_id' => 1, 'timestamp' => time()]],
            ['age' => 30, 'gender' => 'male'],
            10
        );

        // Configurar expectativa para el mock correctamente
        $this->mockEngine->shouldReceive('getUserProfile')
            ->once()
            ->with($userId)
            ->andReturn($profile);

        // Ejecutar caso de uso
        $result = $this->useCase->execute($userId);

        // Verificar resultado
        $this->assertIsArray($result);
        $this->assertArrayHasKey('top_interests', $result);
        $this->assertArrayHasKey('recent_searches', $result);
        $this->assertArrayHasKey('recent_products', $result);
        $this->assertArrayHasKey('demographics', $result);
        $this->assertArrayHasKey('interaction_score', $result);
        $this->assertArrayHasKey('profile_completeness', $result);
    }
}
