<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionConfigurationSeeder extends Seeder
{
    /**
     * Seed the application's database with production-ready configurations.
     * 
     * Uso:
     * php artisan db:seed --class=ProductionConfigurationSeeder
     * 
     * Este seeder inserta TODAS las configuraciones críticas para que el sistema 
     * funcione correctamente en producción.
     */
    public function run(): void
    {
        $this->command->info('🚀 Insertando configuraciones de producción...');

        // Verificar que las tablas existan
        if (!Schema::hasTable('configurations')) {
            $this->command->error('❌ La tabla configurations no existe. Ejecute php artisan migrate primero.');
            return;
        }

        if (!Schema::hasTable('platform_configurations')) {
            $this->command->error('❌ La tabla platform_configurations no existe. Ejecute php artisan migrate primero.');
            return;
        }

        // Insertar todas las configuraciones críticas
        $this->insertCriticalSystemConfigurations();
        $this->insertVolumeDiscountConfigurations();
        $this->insertShippingConfigurations();
        $this->insertPaymentAndTaxConfigurations();
        $this->insertPlatformCommissionConfigurations();
        $this->insertDatafastConfigurations();
        $this->insertRecommendationSystemConfigurations();

        $this->command->info('✅ Configuraciones de producción insertadas correctamente.');
        $this->command->info('');
        $this->command->info('📋 RESUMEN DE CONFIGURACIONES INSERTADAS:');
        $this->command->info('   • Sistema: Marcado como production-ready');
        $this->command->info('   • Descuentos por volumen: 3+=5%, 6+=10%, 12+=15%');
        $this->command->info('   • Envío: $5.00 (gratis desde $50.00)');
        $this->command->info('   • IVA Ecuador: 15%');
        $this->command->info('   • Comisión plataforma: 10%');
        $this->command->info('   • Datafast: Fase 1 habilitado');
        $this->command->info('   • Sistema de recomendaciones: Habilitado');
    }

    /**
     * Configuraciones críticas del sistema
     */
    private function insertCriticalSystemConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'system.production_ready',
                'value' => 'true',
                'description' => 'Sistema configurado para producción - CRÍTICO',
                'group' => 'system',
                'type' => 'boolean',
            ],
            [
                'key' => 'system.environment',
                'value' => 'production',
                'description' => 'Entorno actual del sistema',
                'group' => 'system',
                'type' => 'text',
            ],
            [
                'key' => 'debug.enable_extensive_logging',
                'value' => 'true',
                'description' => 'Logging extensivo para debugging en producción',
                'group' => 'debug',
                'type' => 'boolean',
            ],
            [
                'key' => 'system.maintenance_mode',
                'value' => 'false',
                'description' => 'Modo mantenimiento deshabilitado en producción',
                'group' => 'system',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de descuentos por volumen CRÍTICAS
     */
    private function insertVolumeDiscountConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'volume_discounts.global_enabled',
                'value' => 'true',
                'description' => 'Descuentos por volumen habilitados globalmente - CRÍTICO para cálculos',
                'group' => 'volume_discounts',
                'type' => 'boolean',
            ],
            [
                'key' => 'volume_discounts.global_tiers',
                'value' => '[{"quantity":3,"discount":5,"label":"3+"},{"quantity":6,"discount":10,"label":"6+"},{"quantity":12,"discount":15,"label":"12+"}]',
                'description' => 'Configuración global descuentos por volumen: 3+=5%, 6+=10%, 12+=15% - CRÍTICO',
                'group' => 'volume_discounts',
                'type' => 'json',
            ],
            [
                'key' => 'volume_discounts.enabled',
                'value' => 'true',
                'description' => 'Sistema de descuentos por volumen habilitado',
                'group' => 'volume_discounts',
                'type' => 'boolean',
            ],
            [
                'key' => 'volume_discounts.stackable',
                'value' => 'true',
                'description' => 'Los descuentos por volumen se pueden combinar con descuentos del seller',
                'group' => 'volume_discounts',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de envío CRÍTICAS
     */
    private function insertShippingConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'shipping.enabled',
                'value' => 'true',
                'description' => 'Sistema de envío habilitado - CRÍTICO para checkout',
                'group' => 'shipping',
                'type' => 'boolean',
            ],
            [
                'key' => 'shipping.default_cost',
                'value' => '5.00',
                'description' => 'Costo de envío estándar USD - CRÍTICO para cálculos',
                'group' => 'shipping',
                'type' => 'decimal',
            ],
            [
                'key' => 'shipping.free_threshold',
                'value' => '50.00',
                'description' => 'Umbral para envío gratis USD - CRÍTICO para cálculos',
                'group' => 'shipping',
                'type' => 'decimal',
            ],
            [
                'key' => 'shipping.production_enabled',
                'value' => 'true',
                'description' => 'Envío habilitado en producción',
                'group' => 'shipping',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de pagos e impuestos CRÍTICAS
     */
    private function insertPaymentAndTaxConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'payment.taxRate',
                'value' => '15',
                'description' => 'Tasa de IVA Ecuador 15% - CRÍTICO para cálculos finales',
                'group' => 'payment',
                'type' => 'number',
            ],
            [
                'key' => 'tax.iva_rate',
                'value' => '15',
                'description' => 'IVA Ecuador en porcentaje - CRÍTICO',
                'group' => 'tax',
                'type' => 'number',
            ],
            [
                'key' => 'tax.enabled',
                'value' => 'true',
                'description' => 'Cálculo de impuestos habilitado - CRÍTICO',
                'group' => 'tax',
                'type' => 'boolean',
            ],
            [
                'key' => 'payment.production_tax_rate',
                'value' => '0.15',
                'description' => 'Tasa IVA como decimal 0.15 = 15% - CRÍTICO',
                'group' => 'payment',
                'type' => 'decimal',
            ],
            [
                'key' => 'payment.currencyCode',
                'value' => 'USD',
                'description' => 'Moneda del sistema Ecuador',
                'group' => 'payment',
                'type' => 'text',
            ],
            [
                'key' => 'payment.currencySymbol',
                'value' => '$',
                'description' => 'Símbolo de moneda',
                'group' => 'payment',
                'type' => 'text',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de comisión de plataforma CRÍTICAS
     */
    private function insertPlatformCommissionConfigurations(): void
    {
        // Configuraciones en tabla configurations
        $configurations = [
            [
                'key' => 'platform.commission_rate',
                'value' => '10',
                'description' => 'Comisión de plataforma 10% - CRÍTICO para cálculos seller',
                'group' => 'platform',
                'type' => 'number',
            ],
            [
                'key' => 'platform.production_commission_rate',
                'value' => '0.10',
                'description' => 'Comisión como decimal 0.10 = 10% - CRÍTICO',
                'group' => 'platform',
                'type' => 'decimal',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);

        // Configuraciones en tabla platform_configurations
        $platformConfigs = [
            [
                'key' => 'platform.commission_rate',
                'value' => json_encode(10.0),
                'description' => 'Porcentaje de comisión que cobra la plataforma a los vendedores - CRÍTICO',
                'category' => 'finance',
                'is_active' => true,
            ],
            [
                'key' => 'shipping.single_seller_percentage',
                'value' => json_encode(80.0),
                'description' => 'Porcentaje del envío para un solo seller (80%) - CRÍTICO',
                'category' => 'shipping',
                'is_active' => true,
            ],
            [
                'key' => 'shipping.multiple_sellers_percentage',
                'value' => json_encode(40.0),
                'description' => 'Porcentaje máximo por seller en multiseller (40%) - CRÍTICO',
                'category' => 'shipping',
                'is_active' => true,
            ],
        ];

        foreach ($platformConfigs as $config) {
            $exists = DB::table('platform_configurations')->where('key', $config['key'])->exists();
            if (!$exists) {
                $config['created_at'] = now();
                $config['updated_at'] = now();
                DB::table('platform_configurations')->insert($config);
                $this->command->info("✅ Insertada platform_configuration: {$config['key']}");
            } else {
                $this->command->warn("⚠️  Ya existe platform_configuration: {$config['key']}");
            }
        }
    }

    /**
     * Configuraciones de Datafast para Ecuador
     */
    private function insertDatafastConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'datafast.production_mode',
                'value' => 'phase1',
                'description' => 'Modo Datafast en producción: phase1 (simulación habilitada)',
                'group' => 'datafast',
                'type' => 'text',
            ],
            [
                'key' => 'datafast.phase_1_enabled',
                'value' => 'true',
                'description' => 'Simulación de pagos Fase 1 habilitada - CRÍTICO para testing',
                'group' => 'datafast',
                'type' => 'boolean',
            ],
            [
                'key' => 'datafast.base_url',
                'value' => 'https://ccapi-stg.datafast.com.ec',
                'description' => 'URL base Datafast staging/producción',
                'group' => 'datafast',
                'type' => 'text',
            ],
            [
                'key' => 'datafast.enabled',
                'value' => 'true',
                'description' => 'Gateway Datafast habilitado',
                'group' => 'datafast',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones del sistema de recomendaciones
     */
    private function insertRecommendationSystemConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'recommendations.enabled',
                'value' => 'true',
                'description' => 'Sistema de recomendaciones ML habilitado',
                'group' => 'recommendations',
                'type' => 'boolean',
            ],
            [
                'key' => 'recommendations.track_user_behavior',
                'value' => 'true',
                'description' => 'Tracking de comportamiento de usuario habilitado',
                'group' => 'recommendations',
                'type' => 'boolean',
            ],
            [
                'key' => 'recommendations.max_recommendations',
                'value' => '10',
                'description' => 'Número máximo de recomendaciones por request',
                'group' => 'recommendations',
                'type' => 'number',
            ],
            [
                'key' => 'analytics.track_product_views',
                'value' => 'true',
                'description' => 'Tracking de vistas de productos habilitado',
                'group' => 'analytics',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Helper para insertar configuraciones solo si no existen
     */
    private function insertConfigurationsIfNotExists(array $configurations): void
    {
        foreach ($configurations as $config) {
            $exists = DB::table('configurations')->where('key', $config['key'])->exists();
            
            if (!$exists) {
                $config['created_at'] = now();
                $config['updated_at'] = now();
                DB::table('configurations')->insert($config);
                $this->command->info("✅ Insertada: {$config['key']} = {$config['value']}");
            } else {
                $this->command->warn("⚠️  Ya existe: {$config['key']}");
            }
        }
    }
}