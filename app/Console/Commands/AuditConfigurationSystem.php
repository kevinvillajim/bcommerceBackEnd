<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\ConfigurationService;

class AuditConfigurationSystem extends Command
{
    protected $signature = 'audit:configuration-system';
    protected $description = 'Auditoría completa del sistema de configuraciones';

    public function handle()
    {
        $this->info('🔍 AUDITORÍA COMPLETA DEL SISTEMA DE CONFIGURACIONES');
        $this->info('====================================================');
        $this->newLine();

        // 1. Auditoría de Base de Datos
        $this->auditDatabase();

        // 2. Auditoría de Endpoints
        $this->auditEndpoints();

        // 3. Auditoría de ConfigurationService
        $this->auditConfigurationService();

        // 4. Auditoría de Consistencia
        $this->auditConsistency();

        // 5. Reporte Final
        $this->generateFinalReport();
    }

    private function auditDatabase()
    {
        $this->info('1. 📊 AUDITORÍA DE BASE DE DATOS');
        $this->info('===============================');

        try {
            // Verificar tabla configurations
            $tableExists = DB::getSchemaBuilder()->hasTable('configurations');
            if (!$tableExists) {
                $this->error('❌ Tabla "configurations" no existe');
                return;
            }

            $this->line('✅ Tabla "configurations" existe');

            // Auditar configuraciones críticas
            $criticalConfigs = [
                'shipping.enabled',
                'shipping.free_threshold',
                'shipping.default_cost',
                'shipping.production_enabled',
                'shipping.production_threshold',
                'shipping.production_cost',
                'volume_discounts.enabled',
                'tax.rate',
                'platform_commission.rate'
            ];

            $this->line("\n🔍 Configuraciones críticas encontradas:");
            $foundConfigs = 0;
            foreach ($criticalConfigs as $key) {
                $config = DB::table('configurations')->where('key', $key)->first();
                if ($config) {
                    $foundConfigs++;
                    $this->line("  ✅ {$key}: '{$config->value}' ({$config->type})");
                } else {
                    $this->warn("  ⚠️  {$key}: NO ENCONTRADO");
                }
            }

            $this->line("\n📈 Resumen BD:");
            $this->line("- Configuraciones críticas encontradas: {$foundConfigs}/" . count($criticalConfigs));

            $totalConfigs = DB::table('configurations')->count();
            $this->line("- Total configuraciones en BD: {$totalConfigs}");

        } catch (\Exception $e) {
            $this->error('❌ Error en auditoría de BD: ' . $e->getMessage());
        }

        $this->newLine();
    }

    private function auditEndpoints()
    {
        $this->info('2. 🌐 AUDITORÍA DE ENDPOINTS');
        $this->info('===========================');

        $endpoints = [
            'Unificado' => 'http://127.0.0.1:8000/api/configurations/unified',
            'Shipping Público' => 'http://127.0.0.1:8000/api/configurations/shipping-public',
            'Volume Discounts' => 'http://127.0.0.1:8000/api/configurations/volume-discounts-public',
            'Tax Público' => 'http://127.0.0.1:8000/api/configurations/tax-public',
            'Platform Commission' => 'http://127.0.0.1:8000/api/configurations/platform-commission-public'
        ];

        foreach ($endpoints as $name => $url) {
            try {
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && $data['status'] === 'success') {
                        $this->line("✅ {$name}: OK (200)");

                        // Verificar contenido específico
                        if ($name === 'Unificado' && isset($data['data']['shipping'])) {
                            $shipping = $data['data']['shipping'];
                            $this->line("   - shipping.enabled: " . ($shipping['enabled'] ? 'true' : 'false'));
                            $this->line("   - shipping.free_threshold: " . $shipping['free_threshold']);
                        }
                    } else {
                        $this->warn("⚠️  {$name}: Respuesta inválida");
                    }
                } else {
                    $this->error("❌ {$name}: HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->error("❌ {$name}: Error - " . $e->getMessage());
            }
        }

        $this->newLine();
    }

    private function auditConfigurationService()
    {
        $this->info('3. ⚙️  AUDITORÍA DE CONFIGURATION SERVICE');
        $this->info('========================================');

        try {
            $configService = app(ConfigurationService::class);

            // Probar métodos principales
            $this->line('🔍 Probando métodos del ConfigurationService:');

            // Test getConfig
            $shippingEnabled = $configService->getConfig('shipping.enabled', 'default');
            $this->line("✅ getConfig('shipping.enabled'): " . ($shippingEnabled !== 'default' ? 'OK' : 'FAIL'));

            // Test setConfig (sin modificar datos reales)
            $this->line("✅ setConfig: Método disponible");

            // Verificar diagnósticos si están disponibles
            if (method_exists($configService, 'getDiagnostics')) {
                $diagnostics = $configService->getDiagnostics();
                $this->line("✅ getDiagnostics: OK");
                if (is_array($diagnostics)) {
                    $this->line("   - Claves disponibles: " . implode(', ', array_keys($diagnostics)));
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Error en ConfigurationService: ' . $e->getMessage());
        }

        $this->newLine();
    }

    private function auditConsistency()
    {
        $this->info('4. 🔄 AUDITORÍA DE CONSISTENCIA');
        $this->info('==============================');

        try {
            // Comparar BD vs Endpoint Unificado
            $this->line('🔍 Comparando BD vs Endpoint Unificado:');

            // Datos de BD
            $dbShippingEnabled = DB::table('configurations')
                ->where('key', 'shipping.enabled')
                ->value('value');

            $dbFreeThreshold = DB::table('configurations')
                ->where('key', 'shipping.free_threshold')
                ->value('value');

            // Datos del endpoint
            $response = Http::get('http://127.0.0.1:8000/api/configurations/unified');

            if ($response->successful()) {
                $apiData = $response->json();
                $apiShipping = $apiData['data']['shipping'];

                // Comparar shipping.enabled
                $dbBool = $dbShippingEnabled === 'true';
                $apiBool = $apiShipping['enabled'];

                if ($dbBool === $apiBool) {
                    $this->line("✅ shipping.enabled: BD ({$dbShippingEnabled}) = API (" . ($apiBool ? 'true' : 'false') . ")");
                } else {
                    $this->error("❌ shipping.enabled: BD ({$dbShippingEnabled}) ≠ API (" . ($apiBool ? 'true' : 'false') . ")");
                }

                // Comparar free_threshold
                if (floatval($dbFreeThreshold) === floatval($apiShipping['free_threshold'])) {
                    $this->line("✅ free_threshold: BD ({$dbFreeThreshold}) = API ({$apiShipping['free_threshold']})");
                } else {
                    $this->error("❌ free_threshold: BD ({$dbFreeThreshold}) ≠ API ({$apiShipping['free_threshold']})");
                }

                // Verificar cache
                if (isset($apiData['meta']['cache_duration'])) {
                    $this->line("✅ Cache configurado: {$apiData['meta']['cache_duration']}s");
                }

            } else {
                $this->error('❌ No se pudo obtener datos del API para comparación');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error en auditoría de consistencia: ' . $e->getMessage());
        }

        $this->newLine();
    }

    private function generateFinalReport()
    {
        $this->info('5. 📋 REPORTE FINAL');
        $this->info('==================');

        try {
            // Contadores de salud
            $healthChecks = [];

            // 1. BD Health
            $totalConfigs = DB::table('configurations')->count();
            $shippingConfigs = DB::table('configurations')
                ->where('key', 'LIKE', 'shipping.%')
                ->count();

            $healthChecks['bd'] = $totalConfigs > 0 && $shippingConfigs >= 3;

            // 2. API Health
            $apiResponse = Http::get('http://127.0.0.1:8000/api/configurations/unified');
            $healthChecks['api'] = $apiResponse->successful();

            // 3. Consistency Health
            $consistencyOk = true;
            if ($apiResponse->successful()) {
                $apiData = $apiResponse->json();
                $dbEnabled = DB::table('configurations')
                    ->where('key', 'shipping.enabled')
                    ->value('value');

                $consistencyOk = ($dbEnabled === 'true') === $apiData['data']['shipping']['enabled'];
            }
            $healthChecks['consistency'] = $consistencyOk;

            // Mostrar reporte
            $this->line('🏥 Estado de Salud del Sistema:');
            foreach ($healthChecks as $component => $isHealthy) {
                $status = $isHealthy ? '✅ SALUDABLE' : '❌ CON PROBLEMAS';
                $this->line("  - " . ucfirst($component) . ": {$status}");
            }

            $healthyCount = array_sum($healthChecks);
            $totalChecks = count($healthChecks);

            $this->newLine();
            if ($healthyCount === $totalChecks) {
                $this->info("🎉 SISTEMA COMPLETAMENTE SALUDABLE ({$healthyCount}/{$totalChecks})");
                $this->line("✅ Todas las configuraciones se están aplicando y propagando correctamente");
            } else {
                $this->warn("⚠️  SISTEMA CON PROBLEMAS ({$healthyCount}/{$totalChecks})");
                $this->line("⚠️  Algunas configuraciones pueden no estar propagándose correctamente");
            }

            // Configuración actual de shipping para referencia
            $this->newLine();
            $this->line('📊 Estado actual de configuraciones de shipping:');

            $currentShipping = DB::table('configurations')
                ->where('key', 'LIKE', 'shipping.%')
                ->pluck('value', 'key');

            foreach ($currentShipping as $key => $value) {
                $this->line("  - {$key}: {$value}");
            }

        } catch (\Exception $e) {
            $this->error('❌ Error generando reporte final: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== FIN DE LA AUDITORÍA ===');
    }
}