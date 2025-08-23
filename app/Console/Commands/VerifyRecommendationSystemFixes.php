<?php

namespace App\Console\Commands;

use App\Domain\Formatters\ProductFormatter;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\ProfileEnricherService;
use App\UseCases\Recommendation\GenerateRecommendationsUseCase;
use Illuminate\Console\Command;

class VerifyRecommendationSystemFixes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'recommendation:verify-fixes';

    /**
     * The console command description.
     */
    protected $description = 'Verifica que las correcciones del sistema de recomendaciones funcionen correctamente';

    private ProfileEnricherService $profileEnricher;

    private GenerateRecommendationsUseCase $recommendationsUseCase;

    private ProductFormatter $productFormatter;

    public function __construct(
        ProfileEnricherService $profileEnricher,
        GenerateRecommendationsUseCase $recommendationsUseCase,
        ProductFormatter $productFormatter
    ) {
        parent::__construct();
        $this->profileEnricher = $profileEnricher;
        $this->recommendationsUseCase = $recommendationsUseCase;
        $this->productFormatter = $productFormatter;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando correcciones del sistema de recomendaciones...');
        $this->newLine();

        // Test 1: Verificar que ProductFormatter incluya el campo 'published'
        $this->testProductFormatterFields();

        // Test 2: Verificar que ProfileEnricher devuelva 'new_user' para usuarios sin interacciones
        $this->testProfileEnricherNewUser();

        // Test 3: Verificar que las recomendaciones incluyan todos los campos necesarios
        $this->testRecommendationsFields();

        $this->newLine();
        $this->info('✅ Verificación completada. Ejecuta los tests para confirmar:');
        $this->line('php artisan test tests/Feature/RecommendationSystem/UserPreferenceTrackingTest.php');

        return Command::SUCCESS;
    }

    /**
     * Test 1: Verificar campos del ProductFormatter
     */
    private function testProductFormatterFields(): void
    {
        $this->info('1️⃣ Verificando campos del ProductFormatter...');

        try {
            // Buscar un producto activo para probar
            $product = Product::where('status', 'active')
                ->where('published', true)
                ->first();

            if (! $product) {
                $this->warn('   ⚠️ No hay productos activos para probar');

                return;
            }

            // Formatear con ProductFormatter
            $formatted = $this->productFormatter->formatForApi($product);

            // Verificar campos críticos
            $requiredFields = ['id', 'name', 'price', 'rating', 'rating_count', 'published', 'main_image', 'images'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (! array_key_exists($field, $formatted)) {
                    $missingFields[] = $field;
                }
            }

            if (empty($missingFields)) {
                $this->line('   ✅ Todos los campos requeridos están presentes');
                $this->line('   📊 Published: '.($formatted['published'] ? 'true' : 'false'));
                $this->line("   💰 Price: \${$formatted['price']}");
                $this->line("   ⭐ Rating: {$formatted['rating']}/5 ({$formatted['rating_count']} reviews)");
            } else {
                $this->error('   ❌ Campos faltantes: '.implode(', ', $missingFields));
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
        }
    }

    /**
     * Test 2: Verificar ProfileEnricher para usuarios nuevos
     */
    private function testProfileEnricherNewUser(): void
    {
        $this->info('2️⃣ Verificando ProfileEnricher para usuarios sin interacciones...');

        try {
            // Buscar o crear un usuario sin interacciones
            /** @phpstan-ignore-next-line */
            $testUser = User::firstOrCreate(
                ['email' => 'test.no.interactions@example.com'],
                [
                    'name' => 'Test User No Interactions',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'is_blocked' => false,
                ]
            );

            // Asegurar que no tiene interacciones
            UserInteraction::where('user_id', $testUser->id)->delete();

            // Enriquecer perfil
            $profile = $this->profileEnricher->enrichUserProfile($testUser->id);

            // Verificar resultado
            $primarySegment = $profile['user_segment']['primary_segment'] ?? 'unknown';
            $confidenceScore = $profile['confidence_score'] ?? -1;

            if ($primarySegment === 'new_user' && $confidenceScore === 0) {
                $this->line('   ✅ Usuario sin interacciones correctamente identificado como "new_user"');
                $this->line("   📊 Segmento: {$primarySegment}");
                $this->line("   🎯 Confianza: {$confidenceScore}%");
            } else {
                $this->error("   ❌ Segmento incorrecto: '{$primarySegment}' (esperado: 'new_user')");
                $this->error("   ❌ Confianza incorrecta: {$confidenceScore} (esperado: 0)");
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
        }
    }

    /**
     * Test 3: Verificar campos en recomendaciones
     */
    private function testRecommendationsFields(): void
    {
        $this->info('3️⃣ Verificando campos en recomendaciones...');

        try {
            // Usar un usuario existente con interacciones o crear uno
            $user = User::first();
            if (! $user) {
                $this->warn('   ⚠️ No hay usuarios para probar recomendaciones');

                return;
            }

            // Generar recomendaciones
            $recommendations = $this->recommendationsUseCase->execute($user->id, 3);

            if (empty($recommendations)) {
                $this->warn('   ⚠️ No se generaron recomendaciones');

                return;
            }

            // Verificar primer recomendación
            $firstRec = $recommendations[0];
            $requiredFields = ['id', 'name', 'price', 'rating', 'rating_count', 'published', 'status'];

            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (! array_key_exists($field, $firstRec)) {
                    $missingFields[] = $field;
                }
            }

            if (empty($missingFields)) {
                $this->line('   ✅ Recomendaciones incluyen todos los campos requeridos');
                $this->line('   📦 Total recomendaciones: '.count($recommendations));
                $this->line("   📊 Ejemplo - ID: {$firstRec['id']}, Published: ".($firstRec['published'] ? 'true' : 'false'));
                $this->line("   💰 Precio: \${$firstRec['price']}, Rating: {$firstRec['rating']}/5");
            } else {
                $this->error('   ❌ Campos faltantes en recomendaciones: '.implode(', ', $missingFields));
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
        }
    }
}
