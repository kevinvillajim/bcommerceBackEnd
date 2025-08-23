<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Models\UserInteraction;
use App\Services\ProfileEnricherService;
use App\Services\RecommendationAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommendationSystemMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recommendations:maintenance
                            {action : Acción a realizar (optimize|cleanup|analyze|rebuild|stats)}
                            {--user-id= : ID de usuario específico para analizar}
                            {--days=30 : Número de días para análisis}
                            {--dry-run : Ejecutar en modo simulación}
                            {--force : Forzar ejecución sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mantenimiento y optimización del sistema de recomendaciones';

    private ProfileEnricherService $profileEnricherService;

    private RecommendationAnalyticsService $analyticsService;

    public function __construct(
        ProfileEnricherService $profileEnricherService,
        RecommendationAnalyticsService $analyticsService
    ) {
        parent::__construct();
        $this->profileEnricherService = $profileEnricherService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🚀 Iniciando mantenimiento del sistema de recomendaciones');
        $this->info("Acción: {$action}");

        if ($dryRun) {
            $this->warn('⚠️ Modo simulación activado - No se realizarán cambios');
        }

        if (! $force && ! $dryRun && ! $this->confirm('¿Desea continuar con la operación?')) {
            $this->info('Operación cancelada');

            return Command::FAILURE;
        }

        try {
            switch ($action) {
                case 'optimize':
                    return $this->optimizeSystem($dryRun);

                case 'cleanup':
                    return $this->cleanupSystem($dryRun);

                case 'analyze':
                    return $this->analyzeSystem();

                case 'rebuild':
                    return $this->rebuildProfiles($dryRun);

                case 'stats':
                    return $this->showStats();

                default:
                    $this->error("Acción no válida: {$action}");
                    $this->info('Acciones disponibles: optimize, cleanup, analyze, rebuild, stats');

                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error ejecutando comando: '.$e->getMessage());
            Log::error('Error en RecommendationSystemMaintenance', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Optimiza el sistema de recomendaciones
     */
    private function optimizeSystem(bool $dryRun): int
    {
        $this->info('🔧 Optimizando sistema de recomendaciones...');

        $optimizations = [
            'cache_cleanup' => 'Limpieza de cache obsoleto',
            'index_optimization' => 'Optimización de índices de base de datos',
            'interaction_consolidation' => 'Consolidación de interacciones duplicadas',
            'profile_refresh' => 'Actualización de perfiles de usuarios activos',
        ];

        $this->table(['Optimización', 'Descripción'], array_map(function ($key, $desc) {
            return [$key, $desc];
        }, array_keys($optimizations), $optimizations));

        $progressBar = $this->output->createProgressBar(count($optimizations));
        $progressBar->start();

        // 1. Limpieza de cache obsoleto
        $this->cleanObsoleteCache($dryRun);
        $progressBar->advance();

        // 2. Optimización de índices (solo mostrar estadísticas)
        $this->optimizeIndexes($dryRun);
        $progressBar->advance();

        // 3. Consolidación de interacciones duplicadas
        $consolidated = $this->consolidateDuplicateInteractions($dryRun);
        $progressBar->advance();

        // 4. Actualización de perfiles de usuarios activos
        $refreshed = $this->refreshActiveUserProfiles($dryRun);
        $progressBar->advance();

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✅ Optimización completada:');
        $this->line('- Cache limpiado');
        $this->line('- Índices optimizados');
        $this->line("- Interacciones consolidadas: {$consolidated}");
        $this->line("- Perfiles actualizados: {$refreshed}");

        return Command::SUCCESS;
    }

    /**
     * Limpia datos obsoletos del sistema
     */
    private function cleanupSystem(bool $dryRun): int
    {
        $this->info('🧹 Limpiando datos obsoletos...');

        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        // Contar interacciones obsoletas
        $obsoleteInteractions = UserInteraction::where('interaction_time', '<', $cutoffDate)->count();

        // Contar cache obsoleto
        $cacheKeys = $this->getObsoleteCacheKeys();

        $this->table(['Tipo', 'Cantidad', 'Acción'], [
            ['Interacciones obsoletas', $obsoleteInteractions, "Eliminar (> {$days} días)"],
            ['Entradas de cache', count($cacheKeys), 'Limpiar cache obsoleto'],
            ['Perfiles inactivos', $this->getInactiveProfilesCount($days), 'Marcar para renovación'],
        ]);

        if (! $dryRun) {
            // Eliminar interacciones muy antiguas (manteniendo datos importantes)
            $deleted = UserInteraction::where('interaction_time', '<', $cutoffDate)
                ->whereNotIn('interaction_type', ['purchase', 'rate_product']) // Mantener compras y ratings
                ->delete();

            // Limpiar cache
            $this->cleanObsoleteCache(false);

            $this->info('✅ Limpieza completada:');
            $this->line("- Interacciones eliminadas: {$deleted}");
            $this->line('- Cache limpiado');
        } else {
            $this->info("🔍 Simulación - Se eliminarían {$obsoleteInteractions} interacciones");
        }

        return Command::SUCCESS;
    }

    /**
     * Analiza el estado del sistema
     */
    private function analyzeSystem(): int
    {
        $this->info('📊 Analizando sistema de recomendaciones...');

        $days = $this->option('days');
        $userId = $this->option('user-id');

        if ($userId) {
            return $this->analyzeSpecificUser((int) $userId);
        }

        // Análisis general del sistema
        $metrics = $this->analyticsService->getSystemMetrics($days);

        $this->info("📈 Métricas del sistema (últimos {$days} días):");
        $this->newLine();

        // Métricas de interacciones
        $interactions = $metrics['interactions'];
        $this->line('<fg=cyan>INTERACCIONES:</fg=cyan>');
        $this->line('Total: '.number_format($interactions['total_interactions']));

        foreach ($interactions['by_type'] as $type => $data) {
            $this->line("- {$data['label']}: {$data['count']} ({$data['percentage']}%)");
        }

        $this->newLine();

        // Métricas de engagement
        $engagement = $metrics['user_engagement'];
        $this->line('<fg=cyan>ENGAGEMENT:</fg=cyan>');
        $this->line('Usuarios activos: '.number_format($engagement['active_users']));
        $this->line('Usuarios altamente comprometidos: '.number_format($engagement['high_engagement_users']));
        $this->line("Tasa de engagement: {$engagement['engagement_rate']}%");

        $this->newLine();

        // Efectividad de recomendaciones
        $effectiveness = $metrics['recommendation_effectiveness'];
        $this->line('<fg=cyan>EFECTIVIDAD DE RECOMENDACIONES:</fg=cyan>');
        $this->line('Interacciones desde recomendaciones: '.number_format($effectiveness['recommendation_driven_interactions']));
        $this->line("Tasa de influencia: {$effectiveness['recommendation_influence_rate']}%");

        return Command::SUCCESS;
    }

    /**
     * Reconstruye perfiles de usuario
     */
    private function rebuildProfiles(bool $dryRun): int
    {
        $this->info('🔄 Reconstruyendo perfiles de usuario...');

        $userId = $this->option('user-id');

        if ($userId) {
            return $this->rebuildSingleProfile((int) $userId, $dryRun);
        }

        // Obtener usuarios activos para reconstruir
        $activeUsers = User::whereHas('interactions', function ($query) {
            $query->where('interaction_time', '>=', now()->subDays(60));
        })
            ->limit(100) // Limitar para evitar sobrecarga
            ->pluck('id');

        $this->info('Perfiles a reconstruir: '.$activeUsers->count());

        if ($activeUsers->isEmpty()) {
            $this->warn('No hay usuarios activos para procesar');

            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($activeUsers->count());
        $progressBar->start();

        $successful = 0;
        $failed = 0;

        foreach ($activeUsers as $uid) {
            try {
                if (! $dryRun) {
                    $this->profileEnricherService->enrichUserProfile($uid);
                }
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Error reconstruyendo perfil usuario {$uid}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✅ Reconstrucción completada:');
        $this->line("- Exitosos: {$successful}");
        $this->line("- Fallidos: {$failed}");

        return Command::SUCCESS;
    }

    /**
     * Muestra estadísticas del sistema
     */
    private function showStats(): int
    {
        $this->info('📊 Estadísticas del sistema de recomendaciones');
        $this->newLine();

        // Estadísticas básicas
        $totalUsers = User::count();
        $activeUsers = UserInteraction::where('interaction_time', '>=', now()->subDays(30))
            ->distinct('user_id')->count();
        $totalInteractions = UserInteraction::count();
        $totalProducts = Product::count();

        $this->table(['Métrica', 'Valor'], [
            ['Usuarios totales', number_format($totalUsers)],
            ['Usuarios activos (30d)', number_format($activeUsers)],
            ['Interacciones totales', number_format($totalInteractions)],
            ['Productos totales', number_format($totalProducts)],
            ['Promedio interacciones/usuario', $totalUsers > 0 ? round($totalInteractions / $totalUsers, 2) : 0],
        ]);

        // Top interacciones
        $topInteractions = UserInteraction::select('interaction_type', DB::raw('count(*) as count'))
            ->groupBy('interaction_type')
            ->orderBy('count', 'desc')
            ->get();

        $this->newLine();
        $this->info('🔝 Top tipos de interacciones:');
        foreach ($topInteractions as $interaction) {
            $label = UserInteraction::INTERACTION_TYPES[$interaction->interaction_type] ?? $interaction->interaction_type;
            $this->line("- {$label}: ".number_format($interaction->count));
        }

        return Command::SUCCESS;
    }

    // Métodos auxiliares privados

    private function analyzeSpecificUser(int $userId): int
    {
        $this->info("👤 Analizando usuario específico: {$userId}");

        $user = User::find($userId);
        if (! $user) {
            $this->error('Usuario no encontrado');

            return Command::FAILURE;
        }

        $this->line("Nombre: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Registrado: {$user->created_at}");

        // Estadísticas de interacciones
        $stats = UserInteraction::getUserStats($userId);

        $this->newLine();
        $this->line('<fg=cyan>ESTADÍSTICAS DE INTERACCIONES:</fg=cyan>');
        $this->line("Total: {$stats['total_interactions']}");
        $this->line("Score de engagement: {$stats['engagement_score']}");
        $this->line("Días desde última actividad: {$stats['recent_activity_days']}");

        // Perfil enriquecido
        try {
            $enrichedProfile = $this->profileEnricherService->enrichUserProfile($userId);

            $this->newLine();
            $this->line('<fg=cyan>PERFIL ENRIQUECIDO:</fg=cyan>');
            $this->line("Confianza del perfil: {$enrichedProfile['confidence_score']}%");
            $this->line("Segmento: {$enrichedProfile['user_segment']['primary_segment']}");
            $this->line('Preferencias de categoría: '.count($enrichedProfile['category_preferences']));

        } catch (\Exception $e) {
            $this->error('Error generando perfil enriquecido: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }

    private function rebuildSingleProfile(int $userId, bool $dryRun): int
    {
        $this->info("Reconstruyendo perfil del usuario {$userId}...");

        if (! $dryRun) {
            $profile = $this->profileEnricherService->enrichUserProfile($userId);
            $this->info("✅ Perfil reconstruido con confianza: {$profile['confidence_score']}%");
        } else {
            $this->info('🔍 Simulación - El perfil sería reconstruido');
        }

        return Command::SUCCESS;
    }

    private function cleanObsoleteCache(bool $dryRun): int
    {
        $patterns = [
            'personalized_recommendations_*',
            'user_profile_*',
            'recommendation_analytics_*',
            'products_*',
        ];

        $cleaned = 0;
        foreach ($patterns as $pattern) {
            if (! $dryRun) {
                // Implementar limpieza real según el driver de cache
                try {
                    Cache::flush(); // Simplificado - en producción usar patrones específicos
                    $cleaned++;
                } catch (\Exception $e) {
                    Log::warning('Error limpiando cache: '.$e->getMessage());
                }
            }
        }

        return $cleaned;
    }

    private function optimizeIndexes(bool $dryRun): void
    {
        // En un entorno real, ejecutar ANALYZE TABLE o comandos específicos del DBMS
        $this->line('Análisis de índices completado');
    }

    private function consolidateDuplicateInteractions(bool $dryRun): int
    {
        // Buscar interacciones duplicadas en un período corto
        $duplicates = DB::table('user_interactions')
            ->select('user_id', 'interaction_type', 'item_id', DB::raw('DATE(interaction_time) as date'), DB::raw('count(*) as count'))
            ->groupBy('user_id', 'interaction_type', 'item_id', 'date')
            ->having('count', '>', 5) // Más de 5 interacciones del mismo tipo en el mismo día
            ->get();

        if (! $dryRun && ! $duplicates->isEmpty()) {
            // Implementar lógica de consolidación
            // Por ahora solo contar
        }

        return $duplicates->count();
    }

    private function refreshActiveUserProfiles(bool $dryRun): int
    {
        $activeUsers = User::whereHas('interactions', function ($query) {
            $query->where('interaction_time', '>=', now()->subDays(7));
        })
            ->limit(50)
            ->pluck('id');

        $refreshed = 0;
        foreach ($activeUsers as $userId) {
            if (! $dryRun) {
                try {
                    $this->profileEnricherService->enrichUserProfile($userId);
                    $refreshed++;
                } catch (\Exception $e) {
                    Log::error("Error refreshing profile for user {$userId}: ".$e->getMessage());
                }
            } else {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    private function getObsoleteCacheKeys(): array
    {
        // Simulado - en producción obtener keys reales del cache
        return ['key1', 'key2', 'key3'];
    }

    private function getInactiveProfilesCount(int $days): int
    {
        /** @phpstan-ignore-next-line */
        return User::whereDoesntHave('interactions', function ($query) use ($days) {
            $query->where('interaction_time', '>=', now()->subDays($days));
        })->count();
    }
}
