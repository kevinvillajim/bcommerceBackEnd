<?php

namespace App\Http\Controllers;

use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para obtener versiones de configuración
 * Permite al frontend detectar cuándo hay cambios
 */
class ConfigurationVersionController extends Controller
{
    protected ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener versiones de todas las configuraciones
     */
    public function getVersions(): JsonResponse
    {
        try {
            $versions = [
                'volume_discounts' => $this->configService->getConfig('volume_discounts.version', time()),
                'shipping_config' => $this->configService->getConfig('shipping.version', time()),
                'platform_commission' => $this->configService->getConfig('platform.version', time()),
                'shipping_distribution' => $this->configService->getConfig('shipping_distribution.version', time()),
                // Agregar más configuraciones según sea necesario
            ];

            return response()->json([
                'success' => true,
                'data' => $versions,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener versiones de configuración'
            ], 500);
        }
    }

    /**
     * Obtener versión específica de una configuración
     */
    public function getVersion(string $configType): JsonResponse
    {
        try {
            $version = $this->configService->getConfig("{$configType}.version", time());

            return response()->json([
                'success' => true,
                'data' => [
                    'config_type' => $configType,
                    'version' => $version,
                    'timestamp' => time()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener versión de configuración'
            ], 500);
        }
    }
}