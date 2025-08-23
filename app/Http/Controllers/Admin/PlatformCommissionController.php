<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlatformCommissionController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener configuración de comisión de la plataforma
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                'platform_commission_rate' => $this->configService->getConfig('platform.commission_rate', 10.0),
                'seller_earnings_rate' => 100 - $this->configService->getConfig('platform.commission_rate', 10.0),
                'last_updated' => $this->configService->getConfig('platform.commission_updated_at', now()->toISOString()),
                'enabled' => $this->configService->getConfig('platform.commission_enabled', true),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración de comisión:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar configuración de comisión de la plataforma
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'platform_commission_rate' => 'required|numeric|min:0|max:50', // Máximo 50% de comisión
                'enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $commissionRate = $request->input('platform_commission_rate');
            $enabled = $request->input('enabled');

            // Guardar configuración
            $this->configService->setConfig('platform.commission_rate', $commissionRate);
            $this->configService->setConfig('platform.commission_enabled', $enabled);
            $this->configService->setConfig('platform.commission_updated_at', now()->toISOString());
            
            // ✅ NUEVO: Actualizar versión para invalidar cache del frontend
            $this->configService->setConfig('platform.version', time());

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración de comisión actualizada exitosamente',
                'data' => [
                    'platform_commission_rate' => $commissionRate,
                    'seller_earnings_rate' => 100 - $commissionRate,
                    'enabled' => $enabled,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando configuración de comisión:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Calcular comisión para un monto dado
     */
    public function calculateCommission(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);
            $enabled = $this->configService->getConfig('platform.commission_enabled', true);

            if (!$enabled) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'original_amount' => $amount,
                        'commission_rate' => 0,
                        'commission_amount' => 0,
                        'seller_earnings' => $amount,
                        'commission_enabled' => false,
                    ],
                ]);
            }

            $commissionAmount = ($amount * $commissionRate) / 100;
            $sellerEarnings = $amount - $commissionAmount;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'original_amount' => $amount,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => round($commissionAmount, 2),
                    'seller_earnings' => round($sellerEarnings, 2),
                    'commission_enabled' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculando comisión:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al calcular comisión',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }
}