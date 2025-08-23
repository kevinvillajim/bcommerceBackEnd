<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ShippingDistributionController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener configuración de distribución de envío
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                // Porcentaje máximo que un seller puede recibir del costo de envío (cuando es solo 1 seller)
                'single_seller_max_percentage' => $this->configService->getConfig('shipping_distribution.single_seller_max', 80.0),
                
                // Porcentaje que cada seller recibe cuando hay múltiples sellers (ej: 40% c/u si son 2)
                'multiple_sellers_percentage_each' => $this->configService->getConfig('shipping_distribution.multiple_sellers_each', 40.0),
                
                // Si la distribución está habilitada
                'enabled' => $this->configService->getConfig('shipping_distribution.enabled', true),
                
                'last_updated' => $this->configService->getConfig('shipping_distribution.updated_at', now()->toISOString()),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración de distribución de envío:', [
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
     * Actualizar configuración de distribución de envío
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'single_seller_max_percentage' => 'required|numeric|min:0|max:100',
                'multiple_sellers_percentage_each' => 'required|numeric|min:0|max:50', // Máximo 50% para que entre 2 no sea más del 100%
                'enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $singleSellerMax = $request->input('single_seller_max_percentage');
            $multipleEach = $request->input('multiple_sellers_percentage_each');
            $enabled = $request->input('enabled');

            // Guardar configuración
            $this->configService->setConfig('shipping_distribution.single_seller_max', $singleSellerMax);
            $this->configService->setConfig('shipping_distribution.multiple_sellers_each', $multipleEach);
            $this->configService->setConfig('shipping_distribution.enabled', $enabled);
            $this->configService->setConfig('shipping_distribution.updated_at', now()->toISOString());
            
            // ✅ NUEVO: Actualizar versión para invalidar cache del frontend
            $this->configService->setConfig('shipping_distribution.version', time());

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración de distribución de envío actualizada exitosamente',
                'data' => [
                    'single_seller_max_percentage' => $singleSellerMax,
                    'multiple_sellers_percentage_each' => $multipleEach,
                    'enabled' => $enabled,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando configuración de distribución de envío:', [
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
     * Calcular distribución de envío para una orden
     */
    public function calculateDistribution(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'shipping_cost' => 'required|numeric|min:0',
                'seller_count' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $shippingCost = $request->input('shipping_cost');
            $sellerCount = $request->input('seller_count');
            
            $enabled = $this->configService->getConfig('shipping_distribution.enabled', true);

            if (!$enabled) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'shipping_cost' => $shippingCost,
                        'seller_count' => $sellerCount,
                        'distribution_per_seller' => 0,
                        'total_distributed_to_sellers' => 0,
                        'platform_keeps' => $shippingCost,
                        'distribution_enabled' => false,
                    ],
                ]);
            }

            if ($sellerCount === 1) {
                // Un solo seller: recibe el porcentaje máximo configurado
                $percentage = $this->configService->getConfig('shipping_distribution.single_seller_max', 80.0);
                $sellerAmount = ($shippingCost * $percentage) / 100;
                $platformKeeps = $shippingCost - $sellerAmount;
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'shipping_cost' => $shippingCost,
                        'seller_count' => $sellerCount,
                        'distribution_per_seller' => round($sellerAmount, 2),
                        'total_distributed_to_sellers' => round($sellerAmount, 2),
                        'platform_keeps' => round($platformKeeps, 2),
                        'percentage_per_seller' => $percentage,
                        'distribution_enabled' => true,
                    ],
                ]);
            } else {
                // Múltiples sellers: cada uno recibe el porcentaje configurado
                $percentageEach = $this->configService->getConfig('shipping_distribution.multiple_sellers_each', 40.0);
                $amountPerSeller = ($shippingCost * $percentageEach) / 100;
                $totalDistributed = $amountPerSeller * $sellerCount;
                $platformKeeps = $shippingCost - $totalDistributed;
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'shipping_cost' => $shippingCost,
                        'seller_count' => $sellerCount,
                        'distribution_per_seller' => round($amountPerSeller, 2),
                        'total_distributed_to_sellers' => round($totalDistributed, 2),
                        'platform_keeps' => round($platformKeeps, 2),
                        'percentage_per_seller' => $percentageEach,
                        'distribution_enabled' => true,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error calculando distribución de envío:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al calcular distribución',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }
}