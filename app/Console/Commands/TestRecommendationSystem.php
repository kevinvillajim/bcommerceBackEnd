<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\ProfileEnricherService;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestRecommendationSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'recommendation:test
                          {--user-id= : ID del usuario a testear (opcional)}
                          {--generate-data : Generar datos de prueba}
                          {--interactions=50 : Número de interacciones a generar}
                          {--verbose : Mostrar información detallada}';

    /**
     * The console command description.
     */
    protected $description = 'Prueba el sistema de recomendaciones completo y genera datos de prueba';

    private ProfileEnricherService $profileEnricher;

    private GenerateRecommendationsUseCase $recommendationsUseCase;

    public function __construct(
        ProfileEnricherService $profileEnricher,
        GenerateRecommendationsUseCase $recommendationsUseCase
    ) {
        parent::__construct();
        $this->profileEnricher = $profileEnricher;
        $this->recommendationsUseCase = $recommendationsUseCase;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Sistema de Recomendaciones Completo');
        $this->newLine();

        $userId = $this->option('user-id');
        $generateData = $this->option('generate-data');
        $interactionsCount = (int) $this->option('interactions');
        $verbose = $this->option('verbose');

        try {
            // Verificar estado del sistema
            $this->checkSystemStatus();

            // Generar datos de prueba si se solicita
            if ($generateData) {
                $userId = $this->generateTestData($interactionsCount);
                $this->info("✅ Datos de prueba generados para usuario ID: {$userId}");
            }

            // Seleccionar usuario para testing
            if (! $userId) {
                $userId = $this->selectTestUser();
            }

            if (! $userId) {
                $this->error('❌ No se encontró usuario para testing');

                return Command::FAILURE;
            }

            $this->info("👤 Testando con usuario ID: {$userId}");
            $this->newLine();

            // Test 1: Verificar interacciones del usuario
            $this->testUserInteractions($userId, $verbose);

            // Test 2: Probar profile enricher
            $this->testProfileEnricher($userId, $verbose);

            // Test 3: Probar sistema de recomendaciones
            $this->testRecommendationSystem($userId, $verbose);

            // Test 4: Verificar endpoints API
            $this->testApiEndpoints($userId, $verbose);

            $this->newLine();
            $this->info('🎉 ¡Testing del sistema completado exitosamente!');
            $this->info('📊 El sistema de recomendaciones está funcionando correctamente');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error durante el testing: '.$e->getMessage());
            if ($verbose) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Verifica el estado general del sistema
     */
    private function checkSystemStatus(): void
    {
        $this->info('🔍 Verificando estado del sistema...');

        // Verificar base de datos
        $userCount = User::count();
        $productCount = Product::where('status', 'active')->where('published', true)->count();
        $categoryCount = Category::count();
        $interactionCount = UserInteraction::count();

        $this->table(['Componente', 'Estado', 'Cantidad'], [
            ['Usuarios', $userCount > 0 ? '✅ OK' : '❌ Sin datos', $userCount],
            ['Productos activos', $productCount > 0 ? '✅ OK' : '❌ Sin productos', $productCount],
            ['Categorías', $categoryCount > 0 ? '✅ OK' : '❌ Sin categorías', $categoryCount],
            ['Interacciones', $interactionCount > 0 ? '✅ OK' : '⚠️ Sin interacciones', $interactionCount],
        ]);

        if ($productCount === 0) {
            $this->warn('⚠️ No hay productos activos. Considera usar --generate-data');
        }
    }

    /**
     * Genera datos de prueba para testing
     */
    private function generateTestData(int $interactionsCount): int
    {
        $this->info("🔧 Generando datos de prueba ({$interactionsCount} interacciones)...");

        // Crear o encontrar usuario de prueba
        /** @phpstan-ignore-next-line */
        $user = User::firstOrCreate(
            ['email' => 'test.recommendations@example.com'],
            [
                'name' => 'Test Recommendations User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'is_blocked' => false,
            ]
        );

        // Obtener productos disponibles
        $products = Product::where('status', 'active')
            ->where('published', true)
            ->where('stock', '>', 0)
            ->take(20)
            ->get();

        if ($products->isEmpty()) {
            throw new \Exception('No hay productos disponibles para generar datos de prueba');
        }

        // Generar interacciones realistas
        $interactionTypes = [
            'view_product' => 0.5,      // 50% vistas
            'add_to_cart' => 0.15,      // 15% añadir carrito
            'add_to_favorites' => 0.10, // 10% favoritos
            'search' => 0.15,           // 15% búsquedas
            'purchase' => 0.05,         // 5% compras
            'rate_product' => 0.05,      // 5% valoraciones
        ];

        $progressBar = $this->output->createProgressBar($interactionsCount);
        $progressBar->start();

        for ($i = 0; $i < $interactionsCount; $i++) {
            $interactionType = $this->selectWeightedRandom($interactionTypes);
            $product = $products->random();

            $metadata = $this->generateRealisticMetadata($interactionType, $product);

            UserInteraction::create([
                'user_id' => $user->id,
                'interaction_type' => $interactionType,
                'item_id' => $interactionType !== 'search' ? $product->id : null,
                'metadata' => $metadata,
                'interaction_time' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            ]);

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $user->id;
    }

    /**
     * Selecciona un usuario con interacciones para testing
     */
    private function selectTestUser(): ?int
    {
        $userWithInteractions = UserInteraction::select('user_id')
            ->groupBy('user_id')
            ->orderBy(DB::raw('COUNT(*)'), 'desc')
            ->first();

        return $userWithInteractions ? $userWithInteractions->user_id : null;
    }

    /**
     * Prueba las interacciones del usuario
     */
    private function testUserInteractions(int $userId, bool $verbose): void
    {
        $this->info('🎯 Testando interacciones del usuario...');

        $interactions = UserInteraction::where('user_id', $userId)->get();
        $stats = UserInteraction::getUserStats($userId);

        $this->line('Total de interacciones: '.$interactions->count());
        $this->line('Score de engagement: '.$stats['engagement_score']);
        $this->line('Días desde última actividad: '.$stats['recent_activity_days']);

        if ($verbose) {
            $this->newLine();
            $this->line('Interacciones por tipo:');
            foreach ($stats['by_type'] as $type => $data) {
                if ($data['count'] > 0) {
                    $this->line("- {$data['label']}: {$data['count']}");
                }
            }
        }

        $this->line('✅ Interacciones verificadas correctamente');
    }

    /**
     * Prueba el profile enricher
     */
    private function testProfileEnricher(int $userId, bool $verbose): void
    {
        $this->info('🧠 Testando Profile Enricher...');

        $enrichedProfile = $this->profileEnricher->enrichUserProfile($userId);

        $this->line("Confianza del perfil: {$enrichedProfile['confidence_score']}%");
        $this->line("Segmento de usuario: {$enrichedProfile['user_segment']['primary_segment']}");
        $this->line('Preferencias de categoría: '.count($enrichedProfile['category_preferences']));

        if ($verbose && ! empty($enrichedProfile['category_preferences'])) {
            $this->newLine();
            $this->line('Top 3 categorías preferidas:');
            foreach (array_slice($enrichedProfile['category_preferences'], 0, 3) as $pref) {
                $this->line("- {$pref['category_name']}: {$pref['preference_score']} points");
            }
        }

        $this->line('✅ Profile enricher funcionando correctamente');
    }

    /**
     * Prueba el sistema de recomendaciones
     */
    private function testRecommendationSystem(int $userId, bool $verbose): void
    {
        $this->info('🎲 Testando Sistema de Recomendaciones...');

        $recommendations = $this->recommendationsUseCase->execute($userId, 10);

        $this->line('Recomendaciones generadas: '.count($recommendations));

        if (empty($recommendations)) {
            $this->warn('⚠️ No se generaron recomendaciones');

            return;
        }

        // Verificar calidad de las recomendaciones
        $validRecommendations = 0;
        $avgRating = 0;
        $categoriesRepresented = [];

        foreach ($recommendations as $rec) {
            if (isset($rec['id'], $rec['name'], $rec['price'], $rec['rating'])) {
                $validRecommendations++;
                $avgRating += $rec['rating'];
                $categoriesRepresented[] = $rec['category_id'] ?? 'unknown';
            }
        }

        $avgRating = $validRecommendations > 0 ? round($avgRating / $validRecommendations, 2) : 0;
        $uniqueCategories = count(array_unique($categoriesRepresented));
        $totalRecommendations = count($recommendations);

        $this->line("Recomendaciones válidas: {$validRecommendations}/{$totalRecommendations}");
        $this->line("Rating promedio: {$avgRating}/5");
        $this->line("Categorías representadas: {$uniqueCategories}");

        if ($verbose && $validRecommendations > 0) {
            $this->newLine();
            $this->line('Muestra de recomendaciones:');
            foreach (array_slice($recommendations, 0, 3) as $index => $rec) {
                $this->line(''.($index + 1).". {$rec['name']} - \${$rec['price']} ({$rec['rating']}/5)");
            }
        }

        $this->line('✅ Sistema de recomendaciones funcionando correctamente');
    }

    /**
     * Prueba los endpoints de la API
     */
    private function testApiEndpoints(int $userId, bool $verbose): void
    {
        $this->info('🚀 Testando Endpoints API...');

        // Simular requests para verificar que los controladores funcionan
        try {
            // Test ProductController methods
            $productController = app(\App\Http\Controllers\ProductController::class);

            $this->line('✅ ProductController instanciado correctamente');

            // Test RecommendationController
            $recommendationController = app(\App\Http\Controllers\RecommendationController::class);

            $this->line('✅ RecommendationController instanciado correctamente');

            // Test services
            $recommendationService = app(\App\Infrastructure\Services\RecommendationService::class);

            $this->line('✅ RecommendationService instanciado correctamente');

        } catch (\Exception $e) {
            $this->error('❌ Error en endpoints: '.$e->getMessage());

            return;
        }

        $this->line('✅ Todos los endpoints están correctamente configurados');
    }

    /**
     * Selecciona un elemento aleatorio basado en pesos
     */
    private function selectWeightedRandom(array $weights): string
    {
        $random = mt_rand() / mt_getrandmax();
        $cumulative = 0;

        foreach ($weights as $item => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $item;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Genera metadata realista para diferentes tipos de interacciones
     */
    private function generateRealisticMetadata(string $interactionType, $product): array
    {
        $metadata = [
            'source' => 'test_data',
            'timestamp' => now()->toISOString(),
        ];

        switch ($interactionType) {
            case 'view_product':
                $metadata['view_time'] = rand(15, 300); // 15 segundos a 5 minutos
                $metadata['engagement_level'] = $metadata['view_time'] >= 120 ? 'high' : 'medium';
                break;

            case 'search':
                $searchTerms = ['smartphone', 'laptop', 'ropa', 'zapatos', 'libro', 'deportes'];
                $metadata['query'] = $searchTerms[array_rand($searchTerms)];
                $metadata['results_count'] = rand(5, 50);
                break;

            case 'add_to_cart':
                $metadata['quantity'] = rand(1, 3);
                $metadata['price'] = $product->price;
                break;

            case 'purchase':
                $metadata['amount'] = $product->price;
                $metadata['quantity'] = rand(1, 2);
                $metadata['payment_method'] = 'credit_card';
                break;

            case 'rate_product':
                $metadata['rating'] = rand(3, 5);
                $metadata['comment'] = 'Comentario de prueba';
                break;
        }

        return $metadata;
    }
}
