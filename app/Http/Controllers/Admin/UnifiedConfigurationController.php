<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UnifiedConfigurationController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * 🎯 ENDPOINT UNIFICADO: Todas las configuraciones en una sola llamada
     * Reemplaza 5 requests individuales por 1 request optimizado
     * 
     * GET /api/configurations/unified
     */
    public function getUnifiedConfiguration(): JsonResponse
    {
        try {
            // ✅ OBTENER TODAS LAS CONFIGURACIONES EN UNA SOLA OPERACIÓN
            $unifiedConfig = [
                // Impuestos
                'tax_rate' => $this->configService->getConfig('tax.rate', 15.0) / 100, // Convertir a decimal
                'tax_name' => $this->configService->getConfig('tax.name', 'IVA'),
                
                // Comisión de plataforma
                'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0) / 100, // Convertir a decimal
                'seller_earnings_rate' => (100 - $this->configService->getConfig('platform.commission_rate', 10.0)) / 100,
                
                // Configuración de envío
                'shipping' => [
                    'enabled' => $this->configService->getConfig('shipping.enabled', true),
                    'default_cost' => (float) $this->configService->getConfig('shipping.default_cost', 5.0),
                    'free_threshold' => (float) $this->configService->getConfig('shipping.free_threshold', 50.0),
                    'seller_percentage_single' => $this->configService->getConfig('shipping.seller_percentage_single', 80.0) / 100,
                    'seller_percentage_max_multi' => $this->configService->getConfig('shipping.seller_percentage_max_multi', 40.0) / 100,
                ],
                
                // Distribución de envío (compatibilidad)
                'shipping_distribution' => [
                    'seller_percentage_single' => $this->configService->getConfig('shipping.seller_percentage_single', 80.0) / 100,
                    'seller_percentage_max_multi' => $this->configService->getConfig('shipping.seller_percentage_max_multi', 40.0) / 100,
                    'platform_percentage_single' => (100 - $this->configService->getConfig('shipping.seller_percentage_single', 80.0)) / 100,
                    'platform_percentage_max_multi' => (100 - $this->configService->getConfig('shipping.seller_percentage_max_multi', 40.0)) / 100,
                ],
                
                // Descuentos por volumen (dinámicos desde BD)
                'volume_discounts' => $this->getVolumeDiscounts(),
                
                // Metadatos
                'updated_at' => now()->toISOString(),
                'version' => '1.0.0',
                'is_valid' => true,
            ];

            // Log::debug('✅ UNIFIED CONFIG: Configuración unificada generada exitosamente', [
            //     'configs_included' => array_keys($unifiedConfig),
            //     'volume_discounts_count' => count($unifiedConfig['volume_discounts']),
            // ]);

            return response()->json([
                'status' => 'success',
                'data' => $unifiedConfig,
                'meta' => [
                    'generated_at' => now()->toISOString(),
                    'cache_duration' => 30, // 30 segundos sugeridos
                    'endpoints_unified' => 5, // Reemplaza 5 endpoints
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ UNIFIED CONFIG ERROR: Error obteniendo configuración unificada', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones unificadas: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * 🔧 OBTENER DESCUENTOS POR VOLUMEN DESDE BD
     */
    private function getVolumeDiscounts(): array
    {
        try {
            // Obtener desde la tabla volume_discounts o configuración
            $discountConfig = $this->configService->getConfig('volume_discounts.tiers', null);
            
            if ($discountConfig && is_string($discountConfig)) {
                $decoded = json_decode($discountConfig, true);
                if ($decoded) {
                    return $decoded;
                }
            }

            // Fallback a configuración por defecto si no existe en BD
            return [
                ['quantity' => 3, 'discount' => 5, 'label' => '3+ items'],
                ['quantity' => 5, 'discount' => 8, 'label' => '5+ items'],
                ['quantity' => 6, 'discount' => 10, 'label' => '6+ items'],
                ['quantity' => 10, 'discount' => 15, 'label' => '10+ items'],
            ];

        } catch (\Exception $e) {
            Log::warning('Volume discounts fallback used', ['error' => $e->getMessage()]);
            
            // Fallback seguro
            return [
                ['quantity' => 3, 'discount' => 5, 'label' => '3+ items'],
                ['quantity' => 5, 'discount' => 8, 'label' => '5+ items'],
                ['quantity' => 6, 'discount' => 10, 'label' => '6+ items'],
                ['quantity' => 10, 'discount' => 15, 'label' => '10+ items'],
            ];
        }
    }
}