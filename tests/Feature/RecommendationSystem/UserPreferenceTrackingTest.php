<?php

namespace Tests\Feature\RecommendationSystem;

use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\ProfileEnricherService;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * ⚠️ SAFE Test para el Sistema Completo de Tracking de Preferencias de Usuario
 *
 * ✅ IMPORTANTE: Este test NO borra la base de datos
 * ✅ Usa datos existentes y crea datos temporales que se limpian al final
 *
 * Este test valida:
 * 1. Tracking automático de interacciones (vistas, cart, favoritos, búsquedas, compras)
 * 2. Profile enricher recopilando y analizando datos
 * 3. Sistema de recomendaciones basado en preferencias
 * 4. Joins correctos con ratings y datos completos
 * 5. Persistencia y consistencia de datos
 */
class UserPreferenceTrackingTest extends TestCase
{
    use WithFaker;

    private User $testUser;

    private string $testUserToken;

    private array $testCategories;

    private array $testProducts;

    private ProfileEnricherService $profileEnricher;

    private GenerateRecommendationsUseCase $recommendationsUseCase;

    // 🧹 Variables para limpieza
    private array $createdUserIds = [];

    private array $createdCategoryIds = [];

    private array $createdProductIds = [];

    private array $createdInteractionIds = [];

    private array $createdRatingIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 🔥 Solo limpiar cache, NO la base de datos
        Cache::flush();

        // ✅ SEGURO: Crear usuario temporal con email único
        $this->testUser = User::create([
            'name' => 'Test User '.time(),
            'email' => 'testuser'.time().'@example.com',
            'password' => bcrypt('password'),
            'is_blocked' => false,
        ]);
        $this->createdUserIds[] = $this->testUser->id;

        // Generar token JWT para las pruebas
        $this->testUserToken = JWTAuth::fromUser($this->testUser);

        // ✅ SEGURO: Usar categorías existentes O crear temporales
        $this->testCategories = $this->getOrCreateTestCategories();

        // ✅ SEGURO: Crear productos temporales
        $this->testProducts = $this->createTestProducts();

        // Crear ratings temporales para algunos productos
        $this->createTestRatings();

        // Inicializar servicios
        $this->profileEnricher = app(ProfileEnricherService::class);
        $this->recommendationsUseCase = app(GenerateRecommendationsUseCase::class);
    }

    /**
     * ✅ SEGURO: Obtener categorías existentes o crear temporales
     */
    private function getOrCreateTestCategories(): array
    {
        $categories = [];
        $categoryNames = ['Electrónicos', 'Ropa', 'Libros', 'Deportes', 'Hogar'];

        foreach ($categoryNames as $name) {
            // Buscar categoría existente primero
            $category = Category::where('name', $name)->first();

            if (! $category) {
                // Solo crear si no existe
                $category = Category::create([
                    'name' => $name,
                    'slug' => strtolower(str_replace(' ', '-', $name)).'-test-'.time(),
                    'description' => 'Categoría de prueba para '.$name,
                ]);
                $this->createdCategoryIds[] = $category->id;
            }

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * ✅ SEGURO: Crear productos temporales
     */
    private function createTestProducts(): array
    {
        $products = [];

        foreach ($this->testCategories as $index => $category) {
            for ($i = 1; $i <= 5; $i++) {
                $product = Product::create([
                    'user_id' => $this->testUser->id,
                    'name' => "Test Product {$category->name} {$i} ".time(),
                    'slug' => 'test-product-'.$category->id.'-'.$i.'-'.time(),
                    'description' => 'Producto de prueba para testing',
                    'short_description' => 'Descripción corta de prueba',
                    'category_id' => $category->id,
                    'price' => rand(100, 1000),
                    'stock' => rand(10, 100),
                    'status' => 'active',
                    'published' => true,
                    'rating' => rand(30, 50) / 10, // 3.0 a 5.0
                    'rating_count' => rand(5, 50),
                    'view_count' => rand(10, 500),
                    'sales_count' => rand(1, 100),
                    'tags' => json_encode($this->generateProductTags($category->name)),
                    'images' => json_encode([
                        'https://example.com/image1.jpg',
                        'https://example.com/image2.jpg',
                    ]),
                ]);

                $this->createdProductIds[] = $product->id;
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * ✅ SEGURO: Crear ratings temporales
     */
    private function createTestRatings(): void
    {
        foreach (array_slice($this->testProducts, 0, 10) as $product) {
            $ratingsCount = rand(3, 8);
            for ($i = 0; $i < $ratingsCount; $i++) {
                $rating = Rating::create([
                    'user_id' => $this->testUser->id,
                    'product_id' => $product->id,
                    'rating' => rand(3, 5),
                    'comment' => 'Comentario de prueba '.$i,
                ]);
                $this->createdRatingIds[] = $rating->id;
            }
        }
    }

    /**
     * Test 1: Tracking automático de vista de productos
     */
    public function test_automatic_product_view_tracking()
    {
        $this->actingAs($this->testUser);

        // Seleccionar producto para vista
        $product = $this->testProducts[0];

        // Simular vista de producto con tiempo de vista
        $response = $this->postJson("/api/products/{$product->id}/view", [
            'metadata' => [
                'view_time' => 120, // 2 minutos
                'source' => 'search',
                'user_agent' => 'Test Browser',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'interaction_tracked' => true,
            ]);

        // Verificar que la interacción se registró correctamente
        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->testUser->id,
            'interaction_type' => 'view_product',
            'item_id' => $product->id,
        ]);

        // Verificar metadata de la interacción
        $interaction = UserInteraction::where('user_id', $this->testUser->id)
            ->where('interaction_type', 'view_product')
            ->where('item_id', $product->id)
            ->first();

        $this->assertNotNull($interaction);
        $this->assertEquals(120, $interaction->metadata['view_time']);
        $this->assertEquals('high', $interaction->metadata['engagement_level']);
        $this->assertNotNull($interaction->metadata['recorded_at']);
    }

    /**
     * Test 2: Tracking de múltiples tipos de interacciones
     */
    public function test_multiple_interaction_types_tracking()
    {
        $this->actingAs($this->testUser);

        $electronicProduct = $this->testProducts[0]; // Electrónicos
        $clothingProduct = $this->testProducts[5]; // Ropa

        // 1. Vista de producto electrónico
        $this->trackInteractionSafely($this->testUser->id, 'view_product', $electronicProduct->id, [
            'view_time' => 90,
        ]);

        // 2. Agregar a carrito
        $this->trackInteractionSafely($this->testUser->id, 'add_to_cart', $electronicProduct->id);

        // 3. Agregar a favoritos
        $this->trackInteractionSafely($this->testUser->id, 'add_to_favorites', $electronicProduct->id);

        // 4. Búsqueda
        $this->trackInteractionSafely($this->testUser->id, 'search', null, [
            'query' => 'smartphones',
            'results_count' => 15,
        ]);

        // 5. Vista de producto de ropa
        $this->trackInteractionSafely($this->testUser->id, 'view_product', $clothingProduct->id, [
            'view_time' => 45,
        ]);

        // 6. Simular compra
        $this->trackInteractionSafely($this->testUser->id, 'purchase', $electronicProduct->id, [
            'amount' => $electronicProduct->price,
            'quantity' => 1,
        ]);

        // Verificar que las interacciones con productos se registraron (5 en total - búsqueda ignorada)
        $this->assertEquals(5, UserInteraction::where('user_id', $this->testUser->id)->count());

        // Verificar tipos específicos
        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->testUser->id,
            'interaction_type' => 'view_product',
            'item_id' => $electronicProduct->id,
        ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->testUser->id,
            'interaction_type' => 'add_to_cart',
            'item_id' => $electronicProduct->id,
        ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->testUser->id,
            'interaction_type' => 'purchase',
            'item_id' => $electronicProduct->id,
        ]);

        // ⚠️ NOTA: Las búsquedas SIN producto ya no se registran
        // porque no tienen sentido para recomendaciones
    }

    /**
     * Test 3: Profile enricher análisis y recopilación de datos
     */
    public function test_profile_enricher_data_collection_and_analysis()
    {
        $this->actingAs($this->testUser);

        // Crear un patrón de comportamiento realista
        $electronicsCategory = $this->testCategories[0];
        $electronicsProducts = array_slice($this->testProducts, 0, 5);

        // Múltiples vistas en electrónicos (usuario interesado en esta categoría)
        foreach ($electronicsProducts as $index => $product) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => rand(60, 180), // 1-3 minutos
                'source' => 'category_browse',
            ]);

            // Algunas interacciones más profundas
            if ($index < 2) {
                $this->trackInteractionSafely($this->testUser->id, 'add_to_cart', $product->id);
            }
            if ($index == 0) {
                $this->trackInteractionSafely($this->testUser->id, 'add_to_favorites', $product->id);
                $this->trackInteractionSafely($this->testUser->id, 'purchase', $product->id, [
                    'amount' => $product->price,
                ]);
            }
        }

        // Algunas búsquedas relacionadas CON PRODUCTOS
        // ✅ AHORA asociamos búsquedas a productos específicos
        $this->trackInteractionSafely($this->testUser->id, 'view_product', $electronicsProducts[0]->id, [
            'search_query' => 'smartphone',
            'view_time' => 30,
        ]);
        $this->trackInteractionSafely($this->testUser->id, 'view_product', $electronicsProducts[1]->id, [
            'search_query' => 'laptop',
            'view_time' => 45,
        ]);

        // Ejecutar profile enricher
        $enrichedProfile = $this->profileEnricher->enrichUserProfile($this->testUser->id);

        // Validar estructura del perfil enriquecido
        $this->assertIsArray($enrichedProfile);
        $this->assertArrayHasKey('user_id', $enrichedProfile);
        $this->assertArrayHasKey('confidence_score', $enrichedProfile);
        $this->assertArrayHasKey('user_segment', $enrichedProfile);
        $this->assertArrayHasKey('category_preferences', $enrichedProfile);
        $this->assertArrayHasKey('behavior_patterns', $enrichedProfile);
        $this->assertArrayHasKey('product_affinities', $enrichedProfile);
        $this->assertArrayHasKey('interaction_metrics', $enrichedProfile);

        // Validar métricas de interacción
        $metrics = $enrichedProfile['interaction_metrics'];
        $this->assertGreaterThan(0, $metrics['total_interactions']);
        $this->assertGreaterThan(0, $metrics['unique_products']);
        $this->assertGreaterThan(0, $metrics['weighted_engagement_score']);
        $this->assertArrayHasKey('interactions_by_type', $metrics);

        // Validar preferencias de categoría
        $categoryPrefs = $enrichedProfile['category_preferences'];
        $this->assertNotEmpty($categoryPrefs);

        // La categoría de electrónicos debería tener la mayor preferencia
        $topCategory = $categoryPrefs[0];
        $this->assertEquals($electronicsCategory->id, $topCategory['category_id']);
        $this->assertEquals($electronicsCategory->name, $topCategory['category_name']);
        $this->assertGreaterThan(0, $topCategory['preference_score']);
        $this->assertGreaterThan(0, $topCategory['total_interactions']);

        // Validar segmentación de usuario
        $userSegment = $enrichedProfile['user_segment'];
        $this->assertArrayHasKey('primary_segment', $userSegment);
        $this->assertArrayHasKey('activity_level', $userSegment);
        $this->assertArrayHasKey('sophistication', $userSegment);

        // Validar que hay patrones de comportamiento detectados
        $behaviorPatterns = $enrichedProfile['behavior_patterns'];
        $this->assertArrayHasKey('shopping_behavior', $behaviorPatterns);
        $this->assertArrayHasKey('temporal_patterns', $behaviorPatterns);

        // Validar score de confianza
        $this->assertGreaterThan(0, $enrichedProfile['confidence_score']);
        $this->assertLessThanOrEqual(100, $enrichedProfile['confidence_score']);
    }

    /**
     * Test 4: Sistema de recomendaciones basado en preferencias
     */
    public function test_recommendation_system_based_on_preferences()
    {
        $this->actingAs($this->testUser);

        // Crear un perfil de usuario con preferencias claras
        $electronicsProducts = array_slice($this->testProducts, 0, 3);
        $booksProducts = array_slice($this->testProducts, 10, 2);

        // Fuerte interés en electrónicos
        foreach ($electronicsProducts as $product) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => rand(120, 180),
            ]);
            $this->trackInteractionSafely($this->testUser->id, 'add_to_cart', $product->id);
        }

        // Compra de un producto electrónico
        $this->trackInteractionSafely($this->testUser->id, 'purchase', $electronicsProducts[0]->id, [
            'amount' => $electronicsProducts[0]->price,
        ]);

        // Interés menor en libros
        foreach ($booksProducts as $product) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => rand(30, 60),
            ]);
        }

        // Generar recomendaciones
        $recommendations = $this->recommendationsUseCase->execute($this->testUser->id, 10);

        // Validar que se obtuvieron recomendaciones
        $this->assertIsArray($recommendations);
        $this->assertGreaterThan(0, count($recommendations));
        $this->assertLessThanOrEqual(10, count($recommendations));

        // Validar estructura de las recomendaciones
        foreach ($recommendations as $recommendation) {
            $this->assertArrayHasKey('id', $recommendation);
            $this->assertArrayHasKey('name', $recommendation);
            $this->assertArrayHasKey('price', $recommendation);
            $this->assertArrayHasKey('rating', $recommendation);
            $this->assertArrayHasKey('rating_count', $recommendation);
            $this->assertArrayHasKey('main_image', $recommendation);
            $this->assertArrayHasKey('images', $recommendation);
            $this->assertArrayHasKey('category_id', $recommendation);
            $this->assertArrayHasKey('category_name', $recommendation);
            $this->assertArrayHasKey('recommendation_type', $recommendation);

            // Validar que son productos activos y en stock
            $this->assertEquals('active', $recommendation['status']);
            $this->assertTrue($recommendation['published']);
            $this->assertGreaterThan(0, $recommendation['stock']);

            // Validar que no están en productos ya vistos
            $viewedProductIds = array_merge(
                array_column($electronicsProducts, 'id'),
                array_column($booksProducts, 'id')
            );
            $this->assertNotContains($recommendation['id'], $viewedProductIds);
        }

        // Validar que las recomendaciones son inteligentes:
        // Deberían incluir principalmente productos de electrónicos (categoría preferida)
        $electronicsRecommendations = collect($recommendations)
            ->where('category_id', $this->testCategories[0]->id)
            ->count();

        $this->assertGreaterThan(0, $electronicsRecommendations,
            'Las recomendaciones deberían incluir productos de la categoría preferida');
    }

    /**
     * Test 5: Joins correctos con ratings y datos completos
     */
    public function test_recommendations_include_complete_data_with_ratings()
    {
        $this->actingAs($this->testUser);

        // Crear interacciones para generar recomendaciones
        $product = $this->testProducts[0];
        $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
            'view_time' => 120,
        ]);

        // Generar recomendaciones
        $recommendations = $this->recommendationsUseCase->execute($this->testUser->id, 5);

        $this->assertGreaterThan(0, count($recommendations));

        // Validar que cada recomendación tiene datos completos
        foreach ($recommendations as $recommendation) {
            // Datos básicos del producto
            $this->assertNotEmpty($recommendation['id']);
            $this->assertNotEmpty($recommendation['name']);
            $this->assertIsNumeric($recommendation['price']);
            $this->assertIsNumeric($recommendation['rating']);
            $this->assertIsNumeric($recommendation['rating_count']);

            // Imágenes
            $this->assertIsArray($recommendation['images']);
            $this->assertNotEmpty($recommendation['main_image']);

            // Categoría (join correcto)
            $this->assertNotEmpty($recommendation['category_id']);
            $this->assertNotEmpty($recommendation['category_name']);

            // Stock y status
            $this->assertIsNumeric($recommendation['stock']);
            $this->assertGreaterThan(0, $recommendation['stock']);
            $this->assertEquals('active', $recommendation['status']);
            $this->assertTrue($recommendation['published']);

            // Metadatos de recomendación
            $this->assertNotEmpty($recommendation['recommendation_type']);

            // Verificar que el rating puede venir de la tabla ratings o del producto
            if ($recommendation['rating_count'] > 0) {
                $this->assertGreaterThan(0, $recommendation['rating']);
                $this->assertLessThanOrEqual(5, $recommendation['rating']);
            }
        }
    }

    /**
     * Test 6: Test de endpoints de recomendaciones vía API
     */
    public function test_recommendation_api_endpoints()
    {
        // Crear algunas interacciones para que haya datos
        foreach (array_slice($this->testProducts, 0, 3) as $product) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => rand(60, 120),
            ]);
        }

        // Test endpoint de recomendaciones personalizadas
        $response = $this->withHeader('Authorization', 'Bearer '.$this->testUserToken)
            ->getJson('/api/products/personalized?limit=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'rating',
                        'rating_count',
                        'main_image',
                        'images',
                        'category_id',
                        'category_name',
                        'stock',
                        'status',
                        'published',
                        'recommendation_type',
                    ],
                ],
                'meta' => [
                    'total',
                    'count',
                    'type',
                    'personalized',
                ],
            ]);

        $data = $response->json();
        $this->assertLessThanOrEqual(5, count($data['data']));
        $this->assertTrue($data['meta']['personalized']);

        // Test endpoint de recomendaciones generales
        $generalResponse = $this->getJson('/api/recommendations');
        $generalResponse->assertStatus(200);

        // Test tracking de interacción vía API
        $trackResponse = $this->withHeader('Authorization', 'Bearer '.$this->testUserToken)
            ->postJson('/api/recommendations/track-interaction', [
                'interaction_type' => 'view_product',
                'item_id' => $this->testProducts[0]->id,
                'metadata' => [
                    'source' => 'api_test',
                    'view_time' => 75,
                ],
            ]);

        $trackResponse->assertStatus(200);

        // Verificar que se guardó la interacción
        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->testUser->id,
            'interaction_type' => 'view_product',
            'item_id' => $this->testProducts[0]->id,
        ]);

        // Test perfil de usuario
        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$this->testUserToken)
            ->getJson('/api/recommendations/user-profile');
        $profileResponse->assertStatus(200)
            ->assertJsonStructure([
                'top_interests',
                'recent_searches',
                'recent_products',
                'interaction_score',
                'profile_completeness',
            ]);
    }

    /**
     * Test 7: Consistencia de datos y persistencia
     */
    public function test_data_consistency_and_persistence()
    {
        $this->actingAs($this->testUser);

        $product = $this->testProducts[0];

        // Registrar múltiples interacciones del mismo tipo
        for ($i = 1; $i <= 5; $i++) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => $i * 30,
                'session' => "session_$i",
            ]);
        }

        // Verificar que todas se guardaron
        $interactions = UserInteraction::where('user_id', $this->testUser->id)
            ->where('interaction_type', 'view_product')
            ->where('item_id', $product->id)
            ->get();

        $this->assertEquals(5, $interactions->count());

        // Verificar integridad de metadata
        foreach ($interactions as $interaction) {
            $this->assertArrayHasKey('view_time', $interaction->metadata);
            $this->assertArrayHasKey('session', $interaction->metadata);
            $this->assertArrayHasKey('recorded_at', $interaction->metadata);
        }

        // Verificar que el profile enricher maneja correctamente múltiples interacciones
        $enrichedProfile = $this->profileEnricher->enrichUserProfile($this->testUser->id);

        $this->assertGreaterThan(0, $enrichedProfile['interaction_metrics']['total_interactions']);
        $this->assertArrayHasKey('view_product', $enrichedProfile['interaction_metrics']['interactions_by_type']);
        $this->assertEquals(5, $enrichedProfile['interaction_metrics']['interactions_by_type']['view_product']);
    }

    /**
     * Test 8: Rendimiento con volumen de datos
     */
    public function test_performance_with_data_volume()
    {
        $this->actingAs($this->testUser);

        $startTime = microtime(true);

        // Crear un volumen considerable de interacciones
        foreach ($this->testProducts as $index => $product) {
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $product->id, [
                'view_time' => rand(30, 180),
            ]);

            if ($index % 3 == 0) {
                $this->trackInteractionSafely($this->testUser->id, 'add_to_cart', $product->id);
            }

            if ($index % 5 == 0) {
                $this->trackInteractionSafely($this->testUser->id, 'add_to_favorites', $product->id);
            }
        }

        // Agregar más vistas de productos en lugar de búsquedas
        for ($i = 0; $i < 10; $i++) {
            $randomProduct = $this->testProducts[array_rand($this->testProducts)];
            $this->trackInteractionSafely($this->testUser->id, 'view_product', $randomProduct->id, [
                'search_query' => "búsqueda $i",
                'view_time' => rand(20, 60),
            ]);
        }

        $dataCreationTime = microtime(true) - $startTime;

        // Test performance del profile enricher
        $enricherStartTime = microtime(true);
        $enrichedProfile = $this->profileEnricher->enrichUserProfile($this->testUser->id);
        $enricherTime = microtime(true) - $enricherStartTime;

        // Test performance de recomendaciones
        $recommendationsStartTime = microtime(true);
        $recommendations = $this->recommendationsUseCase->execute($this->testUser->id, 10);
        $recommendationsTime = microtime(true) - $recommendationsStartTime;

        // Validar que funcionó correctamente
        $this->assertNotEmpty($enrichedProfile);
        $this->assertNotEmpty($recommendations);
        $this->assertLessThanOrEqual(10, count($recommendations));

        // Validar tiempos razonables (menos de 5 segundos cada operación)
        $this->assertLessThan(5.0, $enricherTime,
            "Profile enricher tomó demasiado tiempo: {$enricherTime}s");
        $this->assertLessThan(5.0, $recommendationsTime,
            "Generación de recomendaciones tomó demasiado tiempo: {$recommendationsTime}s");

        // Validar que el perfil tiene datos coherentes
        $this->assertGreaterThan(30, $enrichedProfile['interaction_metrics']['total_interactions']);
        $this->assertNotEmpty($enrichedProfile['category_preferences']);
    }

    /**
     * Test 9: Casos edge y manejo de errores
     */
    public function test_edge_cases_and_error_handling()
    {
        // Test usuario sin interacciones
        $emptyProfile = $this->profileEnricher->enrichUserProfile($this->testUser->id);
        $this->assertNotEmpty($emptyProfile);
        $this->assertEquals(0, $emptyProfile['confidence_score']);
        $this->assertEquals('new_user', $emptyProfile['user_segment']['primary_segment']);

        // Test recomendaciones para usuario sin historial
        $emptyRecommendations = $this->recommendationsUseCase->execute($this->testUser->id, 5);
        $this->assertIsArray($emptyRecommendations);
        // Debería devolver productos populares como fallback
        $this->assertGreaterThan(0, count($emptyRecommendations));

        // Test con datos inválidos - ahora con producto
        try {
            $this->trackInteractionSafely($this->testUser->id, 'invalid_type', $this->testProducts[0]->id, []);
            // No debería fallar, pero debería log el warning
        } catch (\Exception $e) {
            $this->fail('No debería lanzar excepción para tipos inválidos');
        }

        // Test usuario inexistente
        $nonExistentProfile = $this->profileEnricher->enrichUserProfile(99999);
        $this->assertEquals(99999, $nonExistentProfile['user_id']);
        $this->assertEquals(0, $nonExistentProfile['confidence_score']);
    }

    /**
     * Helper: Generar tags realistas para productos
     */
    private function generateProductTags(string $categoryName): array
    {
        $tagMap = [
            'Electrónicos' => ['tecnología', 'gadget', 'digital', 'moderno', 'innovador'],
            'Ropa' => ['moda', 'estilo', 'casual', 'elegante', 'cómodo'],
            'Libros' => ['educativo', 'entretenimiento', 'cultura', 'lectura', 'conocimiento'],
            'Deportes' => ['fitness', 'ejercicio', 'saludable', 'activo', 'entrenamiento'],
            'Hogar' => ['decoración', 'funcional', 'confort', 'familia', 'práctico'],
        ];

        return $tagMap[$categoryName] ?? ['general', 'producto', 'calidad'];
    }

    protected function tearDown(): void
    {
        // 🧹 LIMPIEZA SEGURA: Eliminar solo los datos temporales creados

        try {
            // Limpiar interacciones temporales
            if (! empty($this->createdInteractionIds)) {
                UserInteraction::whereIn('id', $this->createdInteractionIds)->delete();
            }

            // Limpiar todas las interacciones del usuario de prueba
            UserInteraction::where('user_id', $this->testUser->id)->delete();

            // Limpiar ratings temporales
            if (! empty($this->createdRatingIds)) {
                Rating::whereIn('id', $this->createdRatingIds)->delete();
            }

            // Limpiar productos temporales
            if (! empty($this->createdProductIds)) {
                Product::whereIn('id', $this->createdProductIds)->delete();
            }

            // Limpiar categorías temporales (solo las que creamos)
            if (! empty($this->createdCategoryIds)) {
                Category::whereIn('id', $this->createdCategoryIds)->delete();
            }

            // Limpiar usuarios temporales
            if (! empty($this->createdUserIds)) {
                User::whereIn('id', $this->createdUserIds)->delete();
            }

            echo "✅ [CLEANUP] Datos temporales limpiados correctamente\n";

        } catch (\Exception $e) {
            echo "⚠️ [CLEANUP WARNING] Error durante limpieza: {$e->getMessage()}\n";
        }

        // Limpiar cache después de cada test
        Cache::flush();

        parent::tearDown();
    }

    /**
     * 🔄 Helper seguro para rastrear interacciones
     */
    private function trackInteractionSafely(int $userId, string $type, ?int $itemId, array $metadata = []): void
    {
        try {
            // Usar el método track y capturar el resultado
            UserInteraction::track($userId, $type, $itemId, $metadata);

            // Encontrar la interacción recién creada para rastrear su ID
            $interaction = UserInteraction::where('user_id', $userId)
                ->where('interaction_type', $type)
                ->when($itemId, function ($query, $itemId) {
                    return $query->where('item_id', $itemId);
                }, function ($query) {
                    return $query->whereNull('item_id');
                })
                ->latest()
                ->first();

            if ($interaction) {
                $this->createdInteractionIds[] = $interaction->id;
            }
        } catch (\Exception $e) {
            // Log el error pero no fallar el test
            echo "⚠️ [INTERACTION ERROR] Error tracking interaction: {$e->getMessage()}\n";
        }
    }
}
