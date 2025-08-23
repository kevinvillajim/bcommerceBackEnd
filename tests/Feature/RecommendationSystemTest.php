<?php

namespace Tests\Feature;

use App\Domain\Interfaces\RecommendationEngineInterface;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserInteraction;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use App\UseCases\Recommendation\GetUserProfileUseCase;
use App\UseCases\Recommendation\TrackUserInteractionsUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RecommendationSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $seller;

    private Category $category1;

    private Category $category2;

    private Product $product1;

    private Product $product2;

    private Product $product3;

    private Product $product4;

    private RecommendationEngineInterface $recommendationEngine;

    private GenerateRecommendationsUseCase $generateRecommendationsUseCase;

    private TrackUserInteractionsUseCase $trackUserInteractionsUseCase;

    private GetUserProfileUseCase $getUserProfileUseCase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios de prueba
        $this->user = User::factory()->create([
            'age' => 30,
            'gender' => 'male',
            'location' => 'Ecuador',
        ]);

        $this->seller = User::factory()->create([
            'age' => 35,
            'gender' => 'female',
            'location' => 'Colombia',
        ]);

        // Generar token JWT para autenticación
        $this->token = JWTAuth::fromUser($this->user);

        // Crear categorías de prueba
        $this->category1 = Category::factory()->create(['name' => 'Tecnología']);
        $this->category2 = Category::factory()->create(['name' => 'Deportes']);

        // Crear productos de prueba con diferentes ratings
        $this->product1 = Product::factory()->create([
            'category_id' => $this->category1->id,
            'name' => 'Smartphone XYZ',
            'price' => 500,
            'rating' => 4.5,
            'rating_count' => 120,
            'tags' => ['smartphone', 'tecnologia', 'movil'],
        ]);

        $this->product2 = Product::factory()->create([
            'category_id' => $this->category1->id,
            'name' => 'Laptop ABC',
            'price' => 1200,
            'rating' => 4.2,
            'rating_count' => 85,
            'tags' => ['laptop', 'computadora', 'tecnologia'],
        ]);

        $this->product3 = Product::factory()->create([
            'category_id' => $this->category2->id,
            'name' => 'Zapatillas Running',
            'price' => 150,
            'rating' => 4.7,
            'rating_count' => 200,
            'tags' => ['deporte', 'running', 'zapatillas'],
        ]);

        $this->product4 = Product::factory()->create([
            'category_id' => $this->category1->id,
            'name' => 'Tablet Pro',
            'price' => 800,
            'rating' => 4.0,
            'rating_count' => 50,
            'tags' => ['tablet', 'tecnologia', 'portatil'],
        ]);

        // Crear ratings reales en la base de datos
        Rating::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product1->id,
            'rating' => 5,
            'comment' => 'Excelente producto',
        ]);

        Rating::create([
            'user_id' => $this->seller->id,
            'product_id' => $this->product1->id,
            'rating' => 4,
            'comment' => 'Muy bueno',
        ]);

        // Obtener instancia del servicio desde el contenedor
        $this->recommendationEngine = app(RecommendationEngineInterface::class);

        // Inicializar casos de uso
        $this->generateRecommendationsUseCase = new GenerateRecommendationsUseCase($this->recommendationEngine);
        $this->trackUserInteractionsUseCase = new TrackUserInteractionsUseCase($this->recommendationEngine);
        $this->getUserProfileUseCase = new GetUserProfileUseCase($this->recommendationEngine);
    }

    #[Test]
    public function it_tracks_all_types_of_user_interactions()
    {
        // Test todas las interacciones especificadas en los requerimientos

        // 1. Vista de producto con tiempo de visualización
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product1->id,
            ['view_time' => 120, 'source' => 'search']
        );

        // 2. Agregar al carrito
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'add_to_cart',
            $this->product1->id,
            ['quantity' => 2, 'price' => 500]
        );

        // 3. Agregar a favoritos
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'add_to_favorites',
            $this->product2->id,
            ['category' => 'Tecnología']
        );

        // 4. Quitar de favoritos
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'remove_from_favorites',
            $this->product2->id,
            ['reason' => 'changed_mind']
        );

        // 5. Búsqueda
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'search',
            null,
            ['term' => 'smartphone', 'results_count' => 15, 'page' => 1]
        );

        // 6. Navegación por categoría
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'browse_category',
            null,
            ['category_id' => $this->category1->id, 'category_name' => 'Tecnología']
        );

        // 7. Mensaje a vendedor
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'message_seller',
            $this->product1->id,
            ['seller_id' => $this->seller->id, 'message_type' => 'inquiry']
        );

        // 8. Compra
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'purchase',
            $this->product1->id,
            ['order_id' => 123, 'quantity' => 1, 'total' => 500]
        );

        // 9. Calificación de producto
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'rate_product',
            $this->product1->id,
            ['rating' => 5, 'comment' => 'Excelente producto']
        );

        // Verificar que todas las interacciones se guardaron
        $this->assertEquals(9, UserInteraction::where('user_id', $this->user->id)->count());

        // Verificar interacciones específicas en la base de datos
        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->user->id,
            'interaction_type' => 'view_product',
            'item_id' => $this->product1->id,
        ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->user->id,
            'interaction_type' => 'purchase',
            'item_id' => $this->product1->id,
        ]);

        // Verificar metadata de búsqueda
        $searchInteraction = UserInteraction::where([
            'user_id' => $this->user->id,
            'interaction_type' => 'search',
        ])->first();

        $this->assertNotNull($searchInteraction);
        $metadata = $searchInteraction->metadata;
        $this->assertEquals('smartphone', $metadata['term']);
        $this->assertEquals(15, $metadata['results_count']);

        // Verificar que el peso de las interacciones se calcula correctamente
        $stats = UserInteraction::getUserStats($this->user->id);
        $this->assertGreaterThan(0, $stats['engagement_score']);
        $this->assertEquals(9, $stats['total_interactions']);
    }

    #[Test]
    public function it_generates_recommendations_with_ratings_and_proper_sorting()
    {
        // Registrar interacciones que muestren preferencia por tecnología
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product1->id,
            ['view_time' => 120] // Vista larga indica alto interés
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'add_to_cart',
            $this->product1->id,
            ['quantity' => 1]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product2->id,
            ['view_time' => 60]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'search',
            null,
            ['term' => 'tecnologia', 'results_count' => 10]
        );

        // Obtener recomendaciones
        $recommendations = $this->generateRecommendationsUseCase->execute($this->user->id, 5);

        // Verificar que hay recomendaciones
        $this->assertNotEmpty($recommendations, 'No se generaron recomendaciones');

        // Verificar estructura completa de las recomendaciones incluyendo ratings
        $firstRecommendation = $recommendations[0];
        $this->assertArrayHasKey('id', $firstRecommendation);
        $this->assertArrayHasKey('name', $firstRecommendation);
        $this->assertArrayHasKey('price', $firstRecommendation);
        $this->assertArrayHasKey('rating', $firstRecommendation);
        $this->assertArrayHasKey('rating_count', $firstRecommendation);
        $this->assertArrayHasKey('category_id', $firstRecommendation);
        $this->assertArrayHasKey('category_name', $firstRecommendation);
        $this->assertArrayHasKey('recommendation_type', $firstRecommendation);
        $this->assertArrayHasKey('main_image', $firstRecommendation);
        $this->assertArrayHasKey('images', $firstRecommendation);

        // Verificar que los ratings son numéricos y válidos
        $this->assertIsFloat($firstRecommendation['rating']);
        $this->assertIsInt($firstRecommendation['rating_count']);
        $this->assertGreaterThanOrEqual(0, $firstRecommendation['rating']);
        $this->assertLessThanOrEqual(5, $firstRecommendation['rating']);

        // Verificar que las recomendaciones incluyen información de categoría
        $this->assertNotNull($firstRecommendation['category_name']);

        // Test de ordenamiento: los productos con mejores ratings e interacciones deben aparecer primero
        if (count($recommendations) > 1) {
            $ratings = array_column($recommendations, 'rating');
            $this->assertTrue($this->isArrayDescendingOrEqual($ratings),
                'Las recomendaciones no están ordenadas por relevancia/rating');
        }
    }

    #[Test]
    public function it_calculates_ratings_from_database_correctly()
    {
        // Obtener recomendaciones que deberían incluir cálculos de rating
        $recommendations = $this->generateRecommendationsUseCase->execute($this->user->id, 5);

        // Encontrar el producto1 en las recomendaciones
        $product1Recommendation = null;
        foreach ($recommendations as $rec) {
            if ($rec['id'] == $this->product1->id) {
                $product1Recommendation = $rec;
                break;
            }
        }

        if ($product1Recommendation) {
            // El product1 tiene 2 ratings (5 y 4), promedio debería ser 4.5
            $expectedRating = (5 + 4) / 2;
            $this->assertEquals($expectedRating, $product1Recommendation['rating'],
                'El rating calculado no coincide con el promedio de la base de datos');
            $this->assertEquals(2, $product1Recommendation['rating_count'],
                'El conteo de ratings no coincide con la base de datos');
        }
    }

    #[Test]
    public function it_retrieves_user_profile_with_comprehensive_interests()
    {
        // Registrar varias interacciones para construir un perfil

        // Búsquedas
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'search',
            0,
            ['term' => 'smartphone', 'results_count' => 5]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'search',
            0,
            ['term' => 'laptop', 'results_count' => 3]
        );

        // Vistas de productos
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product1->id,
            ['view_time' => 90]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product2->id,
            ['view_time' => 60]
        );

        // Obtener perfil de usuario
        $profile = $this->getUserProfileUseCase->execute($this->user->id);

        // Verificar formato y contenido del perfil
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('top_interests', $profile);
        $this->assertArrayHasKey('recent_searches', $profile);
        $this->assertArrayHasKey('recent_products', $profile);
        $this->assertArrayHasKey('demographics', $profile);
        $this->assertArrayHasKey('interaction_score', $profile);
        $this->assertArrayHasKey('profile_completeness', $profile);

        // Verificar que hay intereses relacionados con tecnología
        $this->assertGreaterThan(0, count($profile['top_interests']));
        $this->assertContains('tecnología', array_keys($profile['top_interests']));

        // Verificar que hay búsquedas recientes
        $this->assertGreaterThan(0, count($profile['recent_searches']));
        $searchTerms = array_column($profile['recent_searches'], 'term');
        $this->assertContains('smartphone', $searchTerms);
        $this->assertContains('laptop', $searchTerms);

        // Verificar que hay productos recientes
        $this->assertGreaterThan(0, count($profile['recent_products']));

        // Verificar que el scoring de interacción es mayor a 0
        $this->assertGreaterThan(0, $profile['interaction_score']);

        // Verificar que la completitud del perfil es razonable
        $this->assertGreaterThan(20, $profile['profile_completeness']); // Al menos 20% completo
    }

    #[Test]
    public function it_handles_api_calls_for_recommendations_with_complete_data_structure()
    {
        // Registrar diversas interacciones para el usuario
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product1->id,
            ['view_time' => 90]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'add_to_favorites',
            $this->product2->id,
            ['source' => 'search']
        );

        // Llamar al endpoint de API para recomendaciones usando JWT
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->getJson('/api/recommendations?limit=5');

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'final_price',
                        'rating',
                        'rating_count',
                        'category_id',
                        'category_name',
                        'main_image',
                        'images',
                        'recommendation_type',
                        'stock',
                        'is_in_stock',
                        'seller_id',
                    ],
                ],
            ]);

        $responseData = $response->json();
        $this->assertNotEmpty($responseData['data']);

        // Verificar que cada recomendación tiene la estructura completa
        foreach ($responseData['data'] as $recommendation) {
            $this->assertIsNumeric($recommendation['rating']);
            $this->assertIsInt($recommendation['rating_count']);
            $this->assertNotNull($recommendation['category_name']);
            $this->assertIsArray($recommendation['images']);
        }
    }

    #[Test]
    public function it_tracks_api_interactions_with_enhanced_metadata()
    {
        // Test diferentes tipos de interacciones via API

        // 1. Vista de producto con tiempo extendido
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/recommendations/track-interaction', [
            'interaction_type' => 'view_product',
            'item_id' => $this->product1->id,
            'metadata' => [
                'view_time' => 120,
                'source' => 'recommendation',
                'scroll_percentage' => 85,
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // 2. Búsqueda con resultados
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/recommendations/track-interaction', [
            'interaction_type' => 'search',
            'item_id' => null,
            'metadata' => [
                'term' => 'laptop gaming',
                'results_count' => 25,
                'filters_applied' => ['price' => '500-1500', 'category' => 'tecnologia'],
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // 3. Agregar al carrito
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/recommendations/track-interaction', [
            'interaction_type' => 'add_to_cart',
            'item_id' => $this->product2->id,
            'metadata' => [
                'quantity' => 1,
                'price' => 1200,
                'source' => 'product_page',
            ],
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Verificar que todas las interacciones se guardaron correctamente
        $this->assertEquals(3, UserInteraction::where('user_id', $this->user->id)->count());

        // Verificar metadata específica de la vista del producto
        $viewInteraction = UserInteraction::where([
            'user_id' => $this->user->id,
            'interaction_type' => 'view_product',
        ])->first();

        $this->assertNotNull($viewInteraction);
        $this->assertEquals(120, $viewInteraction->metadata['view_time']);
        $this->assertEquals('recommendation', $viewInteraction->metadata['source']);

        // Verificar que el engagement level se calculó correctamente
        $this->assertEquals('high', $viewInteraction->metadata['engagement_level']);

        // Verificar búsqueda
        $searchInteraction = UserInteraction::where([
            'user_id' => $this->user->id,
            'interaction_type' => 'search',
        ])->first();

        $this->assertEquals('laptop gaming', $searchInteraction->metadata['term']);
        $this->assertEquals(25, $searchInteraction->metadata['results_count']);
    }

    #[Test]
    public function it_retrieves_comprehensive_user_profile_via_api()
    {
        // Construir un perfil robusto con múltiples interacciones
        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'search',
            null,
            ['term' => 'smartphone', 'results_count' => 5]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'view_product',
            $this->product1->id,
            ['view_time' => 180]
        );

        $this->trackUserInteractionsUseCase->execute(
            $this->user->id,
            'browse_category',
            null,
            ['category_id' => $this->category1->id, 'time_spent' => 45]
        );

        // Llamar al endpoint de API para obtener el perfil usando JWT
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->getJson('/api/recommendations/user-profile');

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'top_interests',
                    'recent_searches',
                    'recent_products',
                    'demographics',
                    'interaction_score',
                    'profile_completeness',
                ],
            ]);

        $profileData = $response->json()['data'];

        // Verificar que los intereses incluyen tecnología
        $this->assertNotEmpty($profileData['top_interests']);

        // Verificar que las búsquedas recientes están presentes
        $this->assertNotEmpty($profileData['recent_searches']);
        $this->assertEquals('smartphone', $profileData['recent_searches'][0]['term']);

        // Verificar que los productos recientes están presentes
        $this->assertNotEmpty($profileData['recent_products']);

        // Verificar datos demográficos
        $this->assertEquals(30, $profileData['demographics']['age']);
        $this->assertEquals('male', $profileData['demographics']['gender']);
        $this->assertEquals('Ecuador', $profileData['demographics']['location']);

        // Verificar scoring
        $this->assertGreaterThan(0, $profileData['interaction_score']);
        $this->assertGreaterThan(0, $profileData['profile_completeness']);
    }

    #[Test]
    public function it_handles_high_volume_interactions_efficiently()
    {
        // Simular un usuario muy activo con muchas interacciones
        for ($i = 0; $i < 50; $i++) {
            UserInteraction::track(
                $this->user->id,
                'view_product',
                $this->product1->id,
                ['view_time' => rand(5, 300), 'session_id' => 'test_session_'.$i]
            );
        }

        for ($i = 0; $i < 20; $i++) {
            UserInteraction::track(
                $this->user->id,
                'search',
                null,
                ['term' => 'search_term_'.$i, 'results_count' => rand(1, 50)]
            );
        }

        // Generar recomendaciones debe ser eficiente incluso con muchas interacciones
        $startTime = microtime(true);
        $recommendations = $this->generateRecommendationsUseCase->execute($this->user->id, 10);
        $endTime = microtime(true);

        // Debería ejecutarse en menos de 2 segundos
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(2.0, $executionTime,
            'Las recomendaciones tardan demasiado con muchas interacciones');

        // Debe devolver recomendaciones válidas
        $this->assertNotEmpty($recommendations);
        $this->assertLessThanOrEqual(10, count($recommendations));

        // Verificar estadísticas del usuario
        $stats = UserInteraction::getUserStats($this->user->id);
        $this->assertEquals(70, $stats['total_interactions']);
        $this->assertGreaterThan(0, $stats['engagement_score']);
    }

    #[Test]
    public function it_personalizes_recommendations_based_on_interaction_patterns()
    {
        // Usuario 1: Prefiere tecnología
        $techUser = User::factory()->create();
        UserInteraction::track($techUser->id, 'view_product', $this->product1->id, ['view_time' => 120]);
        UserInteraction::track($techUser->id, 'view_product', $this->product2->id, ['view_time' => 90]);
        UserInteraction::track($techUser->id, 'add_to_cart', $this->product1->id, ['quantity' => 1]);
        UserInteraction::track($techUser->id, 'search', null, ['term' => 'tecnologia']);

        // Usuario 2: Prefiere deportes
        $sportsUser = User::factory()->create();
        UserInteraction::track($sportsUser->id, 'view_product', $this->product3->id, ['view_time' => 150]);
        UserInteraction::track($sportsUser->id, 'add_to_favorites', $this->product3->id, []);
        UserInteraction::track($sportsUser->id, 'search', null, ['term' => 'running']);

        // Obtener recomendaciones para ambos usuarios
        $techRecommendations = $this->generateRecommendationsUseCase->execute($techUser->id, 5);
        $sportsRecommendations = $this->generateRecommendationsUseCase->execute($sportsUser->id, 5);

        // Verificar que las recomendaciones son diferentes y relevantes
        $this->assertNotEmpty($techRecommendations);
        $this->assertNotEmpty($sportsRecommendations);

        // Las recomendaciones deben ser distintas ya que los usuarios tienen preferencias diferentes
        $techProductIds = array_column($techRecommendations, 'id');
        $sportsProductIds = array_column($sportsRecommendations, 'id');

        // Al menos 50% de las recomendaciones deben ser diferentes
        $intersection = array_intersect($techProductIds, $sportsProductIds);
        $this->assertLessThan(count($techProductIds) * 0.5, count($intersection),
            'Las recomendaciones no están suficientemente personalizadas');
    }

    /**
     * Helper method para verificar si un array está en orden descendente o igual
     */
    private function isArrayDescendingOrEqual(array $array): bool
    {
        for ($i = 0; $i < count($array) - 1; $i++) {
            if ($array[$i] < $array[$i + 1]) {
                return false;
            }
        }

        return true;
    }
}
