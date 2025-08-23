<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClearCorruptedCacheCommand extends Command
{
    protected $signature = 'cache:clear-corrupted {--user-id= : Clear cache for specific user ID}';

    protected $description = 'Clear corrupted product recommendations cache';

    public function handle(): int
    {
        $this->info('🧹 [CACHE FIX] Iniciando limpieza de cache corrupto...');

        try {
            $userId = $this->option('user-id');

            if ($userId) {
                // Limpiar cache específico de usuario
                $this->clearUserCache((int) $userId);
            } else {
                // Limpiar todo el cache de recomendaciones
                $this->clearAllRecommendationCache();
            }

            $this->info('✅ [CACHE FIX] Limpieza completada exitosamente');
            $this->info('🔄 [NEXT] El endpoint /products/personalized debería funcionar correctamente ahora');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ [ERROR] Error en limpieza de cache: '.$e->getMessage());
            Log::error('Error en comando de limpieza de cache: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function clearUserCache(int $userId): void
    {
        $this->info("🎯 Limpiando cache del usuario {$userId}...");

        $userCacheKeys = [
            "personalized_recommendations_{$userId}_10",
            "personalized_recommendations_{$userId}_12",
            "personalized_recommendations_{$userId}_5",
            "product_recommendations_{$userId}",
            "user_profile_{$userId}",
        ];

        foreach ($userCacheKeys as $key) {
            if (Cache::forget($key)) {
                $this->line("🗑️ Cache específico limpiado: {$key}");
            }
        }
    }

    private function clearAllRecommendationCache(): void
    {
        $this->info('💥 Limpiando todo el cache de recomendaciones...');

        // Patrones de cache a limpiar
        $patterns = [
            'personalized_recommendations_*',
            'product_recommendations_*',
            'recommendations_*',
            'products_featured_*',
            'products_popular_*',
            'products_trending_*',
            'products_search_*',
        ];

        foreach ($patterns as $pattern) {
            // Como Laravel Cache no soporta wildcards directamente,
            // realizamos un flush completo para estar seguros
            $this->line("🗑️ Preparando limpieza del patrón: {$pattern}");
        }

        // Flush completo del cache
        Cache::flush();
        $this->info('💥 Cache flush completo ejecutado');
    }
}
