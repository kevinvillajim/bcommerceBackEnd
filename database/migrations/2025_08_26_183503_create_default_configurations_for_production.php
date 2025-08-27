<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Inserta configuraciones críticas para producción
     */
    public function up(): void
    {
        // Verificar que las tablas existan antes de insertar datos
        if (!Schema::hasTable('configurations')) {
            throw new Exception('La tabla configurations no existe. Ejecute primero php artisan migrate');
        }

        if (!Schema::hasTable('platform_configurations')) {
            throw new Exception('La tabla platform_configurations no existe. Ejecute primero php artisan migrate');
        }

        // Insertar configuraciones críticas de producción
        $this->insertCriticalConfigurations();
        $this->insertVolumeDiscountConfigurations();
        $this->insertShippingConfigurations();
        $this->insertPaymentConfigurations();
        $this->insertTaxConfigurations();
        $this->insertPlatformCommissionConfigurations();
        $this->insertDatafastConfigurations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar solo las configuraciones críticas insertadas por esta migración
        $criticalKeys = [
            'system.production_ready',
            'system.environment',
            'volume_discounts.global_enabled',
            'volume_discounts.global_tiers',
            'shipping.production_cost',
            'shipping.production_threshold',
            'shipping.production_enabled',
            'payment.production_tax_rate',
            'platform.production_commission_rate',
            'datafast.production_mode',
            'datafast.phase_1_enabled',
            'debug.enable_extensive_logging',
        ];

        DB::table('configurations')->whereIn('key', $criticalKeys)->delete();
    }

    /**
     * Configuraciones críticas del sistema
     */
    private function insertCriticalConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'system.production_ready',
                'value' => 'true',
                'description' => 'Sistema configurado para producción',
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
                'description' => 'Habilitar logging extensivo para debugging en producción',
                'group' => 'debug',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de descuentos por volumen para producción
     */
    private function insertVolumeDiscountConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'volume_discounts.global_enabled',
                'value' => 'true',
                'description' => 'Descuentos por volumen habilitados globalmente',
                'group' => 'volume_discounts',
                'type' => 'boolean',
            ],
            [
                'key' => 'volume_discounts.global_tiers',
                'value' => '[{"quantity":3,"discount":5,"label":"3+"},{"quantity":6,"discount":10,"label":"6+"},{"quantity":12,"discount":15,"label":"12+"}]',
                'description' => 'Configuración global de descuentos por volumen - 3+=5%, 6+=10%, 12+=15%',
                'group' => 'volume_discounts',
                'type' => 'json',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de envío para producción
     */
    private function insertShippingConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'shipping.production_enabled',
                'value' => 'true',
                'description' => 'Envío habilitado en producción',
                'group' => 'shipping',
                'type' => 'boolean',
            ],
            [
                'key' => 'shipping.production_cost',
                'value' => '5.00',
                'description' => 'Costo de envío estándar en producción (USD)',
                'group' => 'shipping',
                'type' => 'decimal',
            ],
            [
                'key' => 'shipping.production_threshold',
                'value' => '50.00',
                'description' => 'Umbral para envío gratis en producción (USD)',
                'group' => 'shipping',
                'type' => 'decimal',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de pagos y tax para producción
     */
    private function insertPaymentConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'payment.production_tax_rate',
                'value' => '0.15',
                'description' => 'Tasa de IVA para producción Ecuador (15% = 0.15)',
                'group' => 'payment',
                'type' => 'decimal',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de impuestos específicas
     */
    private function insertTaxConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'tax.iva_rate',
                'value' => '15',
                'description' => 'Tasa de IVA Ecuador en porcentaje (15%)',
                'group' => 'tax',
                'type' => 'number',
            ],
            [
                'key' => 'tax.enabled',
                'value' => 'true',
                'description' => 'Cálculo de impuestos habilitado',
                'group' => 'tax',
                'type' => 'boolean',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);
    }

    /**
     * Configuraciones de comisión de plataforma
     */
    private function insertPlatformCommissionConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'platform.production_commission_rate',
                'value' => '0.10',
                'description' => 'Comisión de plataforma en producción (10% = 0.10)',
                'group' => 'platform',
                'type' => 'decimal',
            ],
        ];

        $this->insertConfigurationsIfNotExists($configurations);

        // Insertar en platform_configurations si no existe
        $platformConfigs = [
            [
                'key' => 'platform.commission_rate',
                'value' => json_encode(10.0),
                'description' => 'Porcentaje de comisión que cobra la plataforma a los vendedores',
                'category' => 'finance',
                'is_active' => true,
            ],
            [
                'key' => 'shipping.single_seller_percentage',
                'value' => json_encode(80.0),
                'description' => 'Porcentaje del costo de envío que recibe un seller cuando es el único en la orden',
                'category' => 'shipping',
                'is_active' => true,
            ],
            [
                'key' => 'shipping.multiple_sellers_percentage',
                'value' => json_encode(40.0),
                'description' => 'Porcentaje del costo de envío que recibe cada seller cuando hay múltiples en la orden',
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
            }
        }
    }

    /**
     * Configuraciones específicas de Datafast para Ecuador
     */
    private function insertDatafastConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'datafast.production_mode',
                'value' => 'phase1',
                'description' => 'Modo de Datafast en producción (phase1, phase2, live)',
                'group' => 'datafast',
                'type' => 'text',
            ],
            [
                'key' => 'datafast.phase_1_enabled',
                'value' => 'true',
                'description' => 'Habilitar simulación de pagos en Fase 1 de Datafast',
                'group' => 'datafast',
                'type' => 'boolean',
            ],
            [
                'key' => 'datafast.base_url',
                'value' => 'https://ccapi-stg.datafast.com.ec',
                'description' => 'URL base de Datafast para staging/producción',
                'group' => 'datafast',
                'type' => 'text',
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
                
                echo "✅ Insertada configuración: {$config['key']} = {$config['value']}\n";
            } else {
                echo "⚠️  Ya existe configuración: {$config['key']}\n";
            }
        }
    }
};
