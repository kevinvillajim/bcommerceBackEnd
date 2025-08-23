<?php

namespace Tests\Unit;

use App\Domain\Formatters\ProductFormatter;
use App\Domain\Formatters\UserProfileFormatter;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use App\Domain\Services\DemographicProfileGenerator;
use App\Domain\Services\ProfileCompletenessCalculator;
use App\Domain\Services\RecommendationStrategies\StrategyInterface;
use App\Domain\Services\UserProfileEnricher;
use App\Domain\ValueObjects\UserProfile;
use App\Infrastructure\Services\RecommendationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplexRecommendationSystemTest extends TestCase
{
    protected $userProfileRepositoryMock;

    protected $productRepositoryMock;

    protected $userProfileEnricherMock;

    protected $demographicGeneratorMock;

    protected $completenessCalculatorMock;

    protected $productFormatterMock;

    protected $userProfileFormatterMock;

    protected $service;

    protected $userId = 999;

    protected $strategies = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Crear todos los mocks
        $this->userProfileRepositoryMock = Mockery::mock(UserProfileRepositoryInterface::class);
        $this->productRepositoryMock = Mockery::mock(ProductRepositoryInterface::class);
        $this->userProfileEnricherMock = Mockery::mock(UserProfileEnricher::class);
        $this->demographicGeneratorMock = Mockery::mock(DemographicProfileGenerator::class);
        $this->completenessCalculatorMock = Mockery::mock(ProfileCompletenessCalculator::class);
        $this->productFormatterMock = Mockery::mock(ProductFormatter::class);
        $this->userProfileFormatterMock = Mockery::mock(UserProfileFormatter::class);

        // Crear el servicio con los mocks
        $this->service = new RecommendationService(
            $this->userProfileRepositoryMock,
            $this->productRepositoryMock,
            $this->userProfileEnricherMock,
            $this->demographicGeneratorMock,
            $this->completenessCalculatorMock,
            $this->productFormatterMock,
            $this->userProfileFormatterMock
        );

        // Crear estrategias mock para las pruebas complejas
        $this->createMockStrategies();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Crea mocks para las diferentes estrategias de recomendación
     */
    protected function createMockStrategies(): void
    {
        // Estrategia basada en intereses
        $interestStrategy = Mockery::mock(StrategyInterface::class);
        $interestStrategy->shouldReceive('getName')->andReturn('interest_based');
        $interestStrategy->shouldReceive('getRecommendations')->andReturnUsing(
            function ($userId, $userProfile, $viewedProductIds, $limit) {
                return [
                    [
                        'id' => 101,
                        'name' => 'Producto por interés',
                        'price' => 199.99,
                        'recommendation_type' => 'interest_based',
                    ],
                    [
                        'id' => 102,
                        'name' => 'Otro producto por interés',
                        'price' => 299.99,
                        'recommendation_type' => 'interest_based',
                    ],
                ];
            }
        );

        // Estrategia basada en demografía
        $demographicStrategy = Mockery::mock(StrategyInterface::class);
        $demographicStrategy->shouldReceive('getName')->andReturn('demographic');
        $demographicStrategy->shouldReceive('getRecommendations')->andReturnUsing(
            function ($userId, $userProfile, $viewedProductIds, $limit) {
                return [
                    [
                        'id' => 201,
                        'name' => 'Producto demográfico',
                        'price' => 149.99,
                        'recommendation_type' => 'demographic',
                    ],
                    [
                        'id' => 202,
                        'name' => 'Producto demográfico 2',
                        'price' => 249.99,
                        'recommendation_type' => 'demographic',
                    ],
                ];
            }
        );

        // Estrategia basada en productos populares
        $popularStrategy = Mockery::mock(StrategyInterface::class);
        $popularStrategy->shouldReceive('getName')->andReturn('popular');
        $popularStrategy->shouldReceive('getRecommendations')->andReturnUsing(
            function ($userId, $userProfile, $viewedProductIds, $limit) {
                return [
                    [
                        'id' => 301,
                        'name' => 'Producto popular',
                        'price' => 99.99,
                        'recommendation_type' => 'popular',
                    ],
                    [
                        'id' => 302,
                        'name' => 'Producto popular 2',
                        'price' => 129.99,
                        'recommendation_type' => 'popular',
                    ],
                ];
            }
        );

        // Estrategia basada en categorías
        $categoryStrategy = Mockery::mock(StrategyInterface::class);
        $categoryStrategy->shouldReceive('getName')->andReturn('category_based');
        $categoryStrategy->shouldReceive('getRecommendations')->andReturnUsing(
            function ($userId, $userProfile, $viewedProductIds, $limit) {
                return [
                    [
                        'id' => 401,
                        'name' => 'Producto por categoría',
                        'price' => 199.99,
                        'recommendation_type' => 'category_based',
                    ],
                    [
                        'id' => 402,
                        'name' => 'Producto por categoría 2',
                        'price' => 299.99,
                        'recommendation_type' => 'category_based',
                    ],
                ];
            }
        );

        // Guardar estrategias para usarlas en las pruebas
        $this->strategies = [
            'interest' => $interestStrategy,
            'demographic' => $demographicStrategy,
            'popular' => $popularStrategy,
            'category' => $categoryStrategy,
        ];
    }

    #[Test]
    public function it_handles_complete_recommendation_workflow()
    {
        // Simular un flujo completo:
        // 1. Registrar varias interacciones de usuario
        // 2. Obtener un perfil enriquecido
        // 3. Generar recomendaciones con múltiples estrategias
        // 4. Formatear el perfil para API

        // Paso 1: Registrar interacciones de usuario
        $interactions = [
            ['type' => 'view_product', 'itemId' => 1, 'metadata' => ['view_time' => 60]],
            ['type' => 'add_to_cart', 'itemId' => 2, 'metadata' => ['quantity' => 1]],
            ['type' => 'search', 'itemId' => 0, 'metadata' => ['term' => 'smartphone']],
            ['type' => 'purchase', 'itemId' => 3, 'metadata' => ['price' => 299.99]],
        ];

        // Mockear el guardado de interacciones
        $this->userProfileRepositoryMock->shouldReceive('saveUserInteraction')
            ->times(count($interactions))
            ->andReturnUsing(function ($interaction) {
                return $interaction;
            });

        // Registrar las interacciones
        foreach ($interactions as $interaction) {
            $this->service->trackUserInteraction(
                $this->userId,
                $interaction['type'],
                $interaction['itemId'],
                $interaction['metadata']
            );
        }

        // Paso 2: Crear un perfil básico y uno enriquecido
        $basicProfile = new UserProfile(
            ['smartphones' => 7, 'laptops' => 3], // Intereses
            [
                ['term' => 'smartphone', 'timestamp' => time()],
                ['term' => 'iphone', 'timestamp' => time() - 3600],
            ], // Búsquedas
            [
                ['product_id' => 1, 'timestamp' => time()],
                ['product_id' => 2, 'timestamp' => time() - 1800],
                ['product_id' => 3, 'timestamp' => time() - 3600],
            ], // Productos vistos
            ['age' => 35, 'gender' => 'male', 'location' => 'Madrid'], // Demografía
            75 // Puntuación de interacción
        );

        $enhancedProfile = new UserProfile(
            [
                'smartphones' => 7,
                'laptops' => 3,
                'accesorios_moviles' => 5,
                'smartwatches' => 4,
                'tablets' => 3,
            ],
            $basicProfile->getSearchHistory(),
            $basicProfile->getViewedProducts(),
            $basicProfile->getDemographics(),
            $basicProfile->getInteractionScore()
        );

        // Mockear obtener perfil y enriquecimiento
        $this->userProfileRepositoryMock->shouldReceive('buildUserProfile')
            ->with($this->userId)
            ->andReturn($basicProfile);

        $this->userProfileEnricherMock->shouldReceive('enrichProfile')
            ->with(Mockery::type(UserProfile::class), $this->userId)
            ->andReturn($enhancedProfile);

        // Mockear los productos vistos para excluirlos
        $this->userProfileRepositoryMock->shouldReceive('getViewedProductIds')
            ->with($this->userId)
            ->andReturn([1, 2, 3]);

        // Mockear preferencias de categoría
        $this->userProfileRepositoryMock->shouldReceive('getCategoryPreferences')
            ->with($this->userId)
            ->andReturn([
                1 => 10, // Smartphones
                2 => 7,  // Laptops
                3 => 5,   // Accesorios
            ]);

        // Mockear el calculador de completitud
        $this->completenessCalculatorMock->shouldReceive('calculate')
            ->with(Mockery::type(UserProfile::class))
            ->andReturn(85);

        // Mockear el formateador de perfil
        $this->userProfileFormatterMock->shouldReceive('format')
            ->with(Mockery::type(UserProfile::class), $this->userId)
            ->andReturn([
                'top_interests' => [
                    ['name' => 'smartphones', 'type' => 'category', 'strength' => 7],
                    ['name' => 'accesorios_moviles', 'type' => 'category', 'strength' => 5],
                ],
                'recent_searches' => $enhancedProfile->getSearchHistory(),
                'recent_products' => [
                    ['id' => 1, 'name' => 'iPhone 13', 'price' => 999.99],
                    ['id' => 2, 'name' => 'Samsung Galaxy Watch', 'price' => 299.99],
                ],
                'demographics' => $enhancedProfile->getDemographics(),
                'interaction_score' => 75,
                'profile_completeness' => 85,
            ]);

        // Paso 3: Añadir las estrategias al servicio
        foreach ($this->strategies as $strategy) {
            $this->service->addStrategy($strategy);
        }

        // Paso 4: Generar perfil formateado para API
        $formattedProfile = $this->service->getUserProfileFormatted($this->userId);

        // Verificar que el perfil formateado tiene la estructura esperada
        $this->assertIsArray($formattedProfile);
        $this->assertArrayHasKey('top_interests', $formattedProfile);
        $this->assertArrayHasKey('interaction_score', $formattedProfile);
        $this->assertArrayHasKey('profile_completeness', $formattedProfile);
        $this->assertEquals(85, $formattedProfile['profile_completeness']);

        // Paso 5: Generar recomendaciones con varias estrategias
        $recommendations = $this->service->generateRecommendations($this->userId, 6);

        // Verificar que hay recomendaciones y son del tipo correcto
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);

        // Extraer los tipos de recomendación
        $recommendationTypes = array_column($recommendations, 'recommendation_type');

        // Verificar que hay varios tipos de recomendaciones (al menos 2 tipos diferentes)
        $uniqueTypes = array_unique($recommendationTypes);
        $this->assertGreaterThanOrEqual(2, count($uniqueTypes), 'Debería haber al menos dos tipos de recomendaciones');

        // Paso 6: Generar un perfil demográfico
        $demographicInterests = $this->demographicGeneratorMock->shouldReceive('generate')
            ->with(['age' => 35, 'gender' => 'male', 'location' => 'Madrid'])
            ->andReturn([
                'tecnologia' => 8,
                'hogar_inteligente' => 7,
                'electronica' => 6,
            ])
            ->once()
            ->getMock();

        $interests = $this->service->generateDemographicProfile([
            'age' => 35,
            'gender' => 'male',
            'location' => 'Madrid',
        ]);

        // Verificar intereses demográficos
        $this->assertIsArray($interests);
        $this->assertArrayHasKey('tecnologia', $interests);
        $this->assertArrayHasKey('hogar_inteligente', $interests);
        $this->assertEquals(8, $interests['tecnologia']);
    }
}
