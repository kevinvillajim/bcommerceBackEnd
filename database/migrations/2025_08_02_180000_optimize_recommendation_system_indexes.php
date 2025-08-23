<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimización de índices para el sistema de recomendaciones
     * Mejora el rendimiento de consultas del profile enricher
     */
    public function up(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            // ✅ FIXED: Verificar si los índices ya existen antes de crearlos

            // Índice compuesto para consultas frecuentes del profile enricher
            if (! Schema::hasIndex('user_interactions', 'idx_user_type_time')) {
                $table->index(['user_id', 'interaction_type', 'interaction_time'], 'idx_user_type_time');
            }

            // Índice para búsquedas por tipo e item (productos relacionados)
            if (! Schema::hasIndex('user_interactions', 'idx_type_item_time')) {
                $table->index(['interaction_type', 'item_id', 'interaction_time'], 'idx_type_item_time');
            }

            // Índice para consultas de engagement scoring (incluye created_at para particionado temporal)
            if (! Schema::hasIndex('user_interactions', 'idx_user_time_type')) {
                $table->index(['user_id', 'interaction_time', 'interaction_type'], 'idx_user_time_type');
            }

            // Índice para análisis de productos populares
            if (! Schema::hasIndex('user_interactions', 'idx_item_type_time')) {
                $table->index(['item_id', 'interaction_type', 'interaction_time'], 'idx_item_type_time');
            }
        });

        // Optimizar tabla productos para recomendaciones con ratings
        Schema::table('products', function (Blueprint $table) {
            // ✅ FIXED: Verificar si los índices ya existen

            // Índice compuesto para filtros de recomendaciones con ratings
            if (! Schema::hasIndex('products', 'idx_recommendations_filter')) {
                $table->index(['status', 'published', 'stock', 'rating'], 'idx_recommendations_filter');
            }

            // Índice para ordenamiento por popularidad y rating
            if (! Schema::hasIndex('products', 'idx_category_rating_views')) {
                $table->index(['category_id', 'rating', 'view_count'], 'idx_category_rating_views');
            }

            // Índice para búsqueda por tags (JSON)
            if (Schema::hasColumn('products', 'tags')) {
                // Para MySQL 5.7+ con soporte JSON
                try {
                    \DB::statement('CREATE INDEX idx_products_tags ON products ((CAST(tags AS JSON)))');
                } catch (\Exception $e) {
                    // Fallback para versiones anteriores - crear índice normal solo si no existe
                    if (! Schema::hasIndex('products', 'idx_category_status_published')) {
                        $table->index(['category_id', 'status', 'published'], 'idx_category_status_published');
                    }
                }
            }
        });

        // Optimizar tabla ratings para cálculos de recomendaciones
        Schema::table('ratings', function (Blueprint $table) {
            // ✅ FIXED: Verificar si los índices ya existen

            // Índice compuesto para cálculo de ratings promedio
            if (! Schema::hasIndex('ratings', 'idx_product_rating_date')) {
                $table->index(['product_id', 'rating', 'created_at'], 'idx_product_rating_date');
            }

            // Índice para ratings de usuario (historial)
            if (! Schema::hasIndex('ratings', 'idx_user_date_rating')) {
                $table->index(['user_id', 'created_at', 'rating'], 'idx_user_date_rating');
            }
        });

        // Optimizar tabla categories para navegación rápida
        Schema::table('categories', function (Blueprint $table) {
            // ✅ FIXED: Verificar columnas que realmente existen
            // La tabla categories usa 'is_active' no 'status'

            // Índice para categorías activas
            if (Schema::hasColumn('categories', 'is_active') && ! Schema::hasIndex('categories', 'idx_category_active_name')) {
                $table->index(['is_active', 'name'], 'idx_category_active_name');
            }

            // Índice para jerarquía de categorías (si existe parent_id)
            if (Schema::hasColumn('categories', 'parent_id') && Schema::hasColumn('categories', 'is_active') && ! Schema::hasIndex('categories', 'idx_parent_active')) {
                $table->index(['parent_id', 'is_active'], 'idx_parent_active');
            }
        });
    }

    /**
     * Revertir las optimizaciones
     */
    public function down(): void
    {
        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropIndex('idx_user_type_time');
            $table->dropIndex('idx_type_item_time');
            $table->dropIndex('idx_user_time_type');
            $table->dropIndex('idx_item_type_time');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_recommendations_filter');
            $table->dropIndex('idx_category_rating_views');

            if (Schema::hasIndex('products', 'idx_category_status_published')) {
                $table->dropIndex('idx_category_status_published');
            }
        });

        // Eliminar índice JSON si existe
        try {
            \DB::statement('DROP INDEX idx_products_tags ON products');
        } catch (\Exception $e) {
            // Ignorar si no existe
        }

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex('idx_product_rating_date');
            $table->dropIndex('idx_user_date_rating');
        });

        Schema::table('categories', function (Blueprint $table) {
            // ✅ FIXED: Actualizar nombres de índices
            if (Schema::hasIndex('categories', 'idx_category_active_name')) {
                $table->dropIndex('idx_category_active_name');
            }

            if (Schema::hasIndex('categories', 'idx_parent_active')) {
                $table->dropIndex('idx_parent_active');
            }
        });
    }
};
